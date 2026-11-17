#!/usr/bin/env python3
"""Build and sign Windows manager web releases from the current working tree."""

import argparse
import base64
import hashlib
import json
import os
from pathlib import Path
import re
import shutil
import subprocess
import tempfile
import zipfile

PROJECT = Path(__file__).resolve().parents[1]
DIRECTORIES = {'app', 'bootstrap', 'config', 'database', 'docker', 'lang', 'public', 'resources', 'routes', 'storage'}
ROOT_FILES = {'artisan', 'composer.json', 'composer.lock', 'package.json', 'package-lock.json', 'vite.config.js', '.env.example', '.dockerignore', 'LICENSE', 'README.md'}


def command(arguments, **options):
    """Run release tools with argument arrays and propagate failures."""
    return subprocess.run(arguments, check=True, **options)


def permitted(relative):
    """Allow runtime source and templates while rejecting local state and secrets."""
    parts = relative.parts
    if not parts or (parts[0] not in DIRECTORIES and relative.as_posix() not in ROOT_FILES):
        return False
    if any(part in {'.git', 'node_modules', 'vendor', '__pycache__'} for part in parts):
        return False
    if relative.name.startswith('.env') and relative.name != '.env.example':
        return False
    if relative.suffix.lower() in {'.pem', '.key', '.pfx', '.p12', '.bak', '.log'}:
        return False
    if parts[0] == 'storage':
        return relative.name == '.gitignore'
    if parts[:2] in {('bootstrap', 'cache'), ('public', 'build'), ('public', 'storage')}:
        return relative.name == '.gitignore'
    return relative.as_posix() != 'public/hot'


def stage_source(destination):
    """Snapshot tracked and nonignored new runtime files, including unsaved Git changes."""
    result = subprocess.check_output(['git', '-C', str(PROJECT), 'ls-files', '--cached', '--others', '--exclude-standard', '-z'])
    for filename in sorted(set(os.fsdecode(result).split('\0'))):
        if not filename:
            continue
        relative = Path(filename)
        source = PROJECT / relative
        if not permitted(relative) or not source.is_file() or source.is_symlink():
            continue
        if any(parent.is_symlink() for parent in source.parents if parent != PROJECT):
            continue
        target = destination / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(source, target)
    docker_ignore = destination / '.dockerignore'
    with docker_ignore.open('a') as stream:
        stream.write('\n!.env.example\n!docker/.env.example\n')
    for name in ('storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'bootstrap/cache'):
        directory = destination / name
        directory.mkdir(parents=True, exist_ok=True)
        (directory / '.gitignore').write_text('*\n!.gitignore\n')


def package(runtime, output, version, cdn):
    """Write split production archives and the manager schema-1 manifest."""
    for filename in ('artisan', 'vendor/autoload.php', 'public/build/manifest.json', '.env.example', 'docker/.env.example'):
        if not (runtime / filename).is_file():
            raise ValueError('Incomplete runtime: ' + filename)
    installed = json.loads((runtime / 'vendor/composer/installed.json').read_text())
    if not isinstance(installed, dict) or installed.get('dev') is not False:
        raise ValueError('Release vendor must be installed with --no-dev.')
    registry = (runtime / 'config/game.php').read_text()
    section = re.search(r"'version_profiles'\s*=>\s*\[(.*?)\]", registry, re.S)
    profiles = re.findall(r"'([a-zA-Z0-9_]+)'\s*=>", section[1]) if section else []
    if not profiles:
        raise ValueError('No game profiles found in registry.')
    artifacts = {}
    for name in ('application', 'vendor'):
        archive_path = output / (name + '.zip')
        with zipfile.ZipFile(archive_path, 'w', zipfile.ZIP_DEFLATED, compresslevel=6) as archive:
            for root, directories, filenames in os.walk(runtime, followlinks=False):
                directories[:] = sorted(directory for directory in directories if not (Path(root) / directory).is_symlink())
                for filename in sorted(filenames):
                    file = Path(root) / filename
                    relative = file.relative_to(runtime)
                    if file.is_symlink():
                        continue
                    is_vendor = relative.parts[0] == 'vendor'
                    if (name == 'vendor') != is_vendor:
                        continue
                    is_build = relative.parts[:2] == ('public', 'build')
                    if not is_vendor and not is_build and not permitted(relative):
                        continue
                    archive.write(file, relative.as_posix())
        with archive_path.open('rb') as stream:
            digest = hashlib.file_digest(stream, 'sha256').hexdigest()
        artifacts[name] = {'url': f'{cdn.rstrip("/")}/muonline-web/releases/v{version}/{archive_path.name}', 'sha256': digest, 'size': archive_path.stat().st_size}
    manifest = {'schema': 1, 'kind': 'web', 'version': version, 'compatibility': {'managerMin': '0.1.0', 'runtime': 'wsl2-docker-v1', 'php': '8.5', 'database': 'sqlsrv', 'architecture': 'amd64'}, 'profiles': profiles, 'artifacts': artifacts}
    (output / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n')


def validate_key(key_file):
    """Extract the public key and require Ed25519 before a costly build."""
    public_key = subprocess.check_output(['openssl', 'pkey', '-in', str(key_file), '-pubout'])
    with tempfile.TemporaryDirectory(prefix='mu-key-check-') as temporary:
        public_file = Path(temporary) / 'public.pem'
        public_file.write_bytes(public_key)
        description = subprocess.check_output(['openssl', 'pkey', '-pubin', '-in', str(public_file), '-text', '-noout'], text=True)
        if 'ED25519' not in description:
            raise ValueError('An Ed25519 release signing key is required.')
    return public_key


def sign_manifest(output, key_file, key_id, public_key):
    """Sign exact manifest bytes and verify before publishing the detached envelope."""
    manifest = output / 'manifest.json'
    with tempfile.TemporaryDirectory(prefix='mu-release-sign-') as temporary:
        signature_file = Path(temporary) / 'signature'
        public_file = Path(temporary) / 'public.pem'
        public_file.write_bytes(public_key)
        command(['openssl', 'pkeyutl', '-sign', '-rawin', '-inkey', str(key_file), '-in', str(manifest), '-out', str(signature_file)])
        command(['openssl', 'pkeyutl', '-verify', '-rawin', '-pubin', '-inkey', str(public_file), '-in', str(manifest), '-sigfile', str(signature_file)])
        envelope = {'algorithm': 'Ed25519', 'keyId': key_id, 'signature': base64.b64encode(signature_file.read_bytes()).decode('ascii')}
    (output / 'manifest.json.sig').write_text(json.dumps(envelope, indent=2) + '\n')
    (output / 'release.public.pem').write_bytes(public_key)


def main():
    """Collect signing configuration, build an isolated runtime and publish local artifacts."""
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('version', help='Release version, for example 1.3.0')
    parser.add_argument('--cdn', default='https://cdn.interweb.bg', help='Public CDN origin, without bucket name')
    parser.add_argument('--key-file', default=os.environ.get('RELEASE_SIGNING_KEY_FILE'))
    parser.add_argument('--key-id', default=os.environ.get('RELEASE_KEY_ID'))
    args = parser.parse_args()
    if not re.fullmatch(r'\d+\.\d+\.\d+', args.version) or not args.cdn.startswith('https://'):
        parser.error('Use a major.minor.patch version and an HTTPS CDN origin.')
    for tool in ('git', 'docker', 'openssl'):
        if not shutil.which(tool):
            parser.error('Required command is missing: ' + tool)
    key_id = args.key_id or input('Release key ID [production-2026]: ').strip() or 'production-2026'
    if not re.fullmatch(r'[a-zA-Z0-9_-]{1,64}', key_id):
        parser.error('Key ID must contain 1–64 letters, digits, underscores or hyphens.')
    default_key = Path.home() / '.config/muonline-web/release.private.pem'
    key_path = args.key_file or input(f'Local Ed25519 private key path [{default_key}]: ').strip() or str(default_key)
    key_file = Path(key_path).expanduser().resolve()
    if key_file.is_relative_to(PROJECT):
        parser.error('Keep the private key outside the project and release archives.')
    if not key_file.exists():
        if input(f'Generate a new Ed25519 key at {key_file}? [y/N]: ').strip().lower() != 'y':
            parser.error('A release signing key is required.')
        key_file.parent.mkdir(parents=True, exist_ok=True, mode=0o700)
        descriptor = os.open(key_file, os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        os.close(descriptor)
        command(['openssl', 'genpkey', '-algorithm', 'ED25519', '-out', str(key_file)])
    public_key = validate_key(key_file)
    print('Building the CURRENT working tree, including uncommitted runtime changes. Local .env, dependencies and uploads are excluded.', flush=True)
    image = 'muonline-web-release:' + os.urandom(6).hex()
    container = None
    image_built = False
    with tempfile.TemporaryDirectory(prefix='muonline-release-') as temporary:
        staging = Path(temporary)
        source = staging / 'source'
        source.mkdir()
        stage_source(source)
        runtime = staging / 'runtime'
        runtime.mkdir()
        output = staging / 'output'
        output.mkdir()
        try:
            command(['docker', 'build', '--target', 'production', '--tag', image, '--file', str(source / 'docker/php/Dockerfile'), str(source)])
            image_built = True
            container = subprocess.check_output(['docker', 'create', image], text=True).strip()
            command(['docker', 'cp', container + ':/var/www/html/.', str(runtime)])
            package(runtime, output, args.version, args.cdn)
            sign_manifest(output, key_file, key_id, public_key)
            destination = PROJECT / 'deploy'
            for file in output.iterdir():
                shutil.copy2(file, destination / file.name)
        finally:
            if container:
                command(['docker', 'rm', container], stdout=subprocess.DEVNULL)
            if image_built:
                command(['docker', 'image', 'rm', image], stdout=subprocess.DEVNULL)
    print('Ready in deploy/: application.zip, vendor.zip, manifest.json, manifest.json.sig')
    print(f'Pin release.public.pem in manager trust configuration using key ID {key_id}.')
    print('Upload the two ZIPs first, then manifest.json and manifest.json.sig. Nothing was uploaded.')


if __name__ == '__main__':
    try:
        main()
    except (ValueError, subprocess.CalledProcessError, EOFError, KeyboardInterrupt) as exception:
        raise SystemExit(str(exception) or 'Release cancelled.')
