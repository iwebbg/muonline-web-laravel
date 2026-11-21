"""Verify the release artifact boundary independently of Docker and live databases."""

import hashlib
import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import zipfile

SPEC = importlib.util.spec_from_file_location('build_release', Path(__file__).with_name('build-release.py'))
RELEASE = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(RELEASE)


class ReleaseArchiveTest(unittest.TestCase):
    """Keep runtime-owned storage outside the signed application artifact."""

    def test_storage_paths_are_rejected_including_placeholders(self):
        """Reject storage even when the file is only a tracked .gitignore."""
        for filename in ('storage/.gitignore', 'storage/app/public/.gitignore', 'storage/framework/cache/data/.gitignore', 'storage/logs/laravel.log'):
            with self.subTest(filename=filename):
                self.assertFalse(RELEASE.permitted(Path(filename)))

    def test_packaging_excludes_storage_and_preserves_runtime_artifacts(self):
        """Keep build-time storage out of ZIPs while hashing the exact resulting bytes."""
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            runtime = root / 'runtime'
            output = root / 'output'
            output.mkdir()
            files = {
                'artisan': '<?php',
                'config/game.php': "<?php return ['version_profiles' => ['mudevs_season_21' => Profile::class]];",
                'vendor/autoload.php': '<?php',
                'vendor/composer/installed.json': '{"dev": false}',
                'public/build/manifest.json': '{}',
                '.env.example': 'APP_NAME=MuOnline',
                'docker/.env.example': 'DOCKER_ENV=local',
                'storage/.gitignore': '*',
                'storage/framework/cache/data/.gitignore': '*',
                'storage/framework/views/generated.php': 'cached view',
                'storage/app/public/upload.png': 'private runtime upload',
                '.env': 'PRIVATE_SECRET=value',
                'docker/.env': 'PRIVATE_SECRET=value',
                'bootstrap/cache/config.php': '<?php /* private cached config */',
                'bootstrap/cache/.gitignore': '*',
            }
            for filename, contents in files.items():
                target = runtime / filename
                target.parent.mkdir(parents=True, exist_ok=True)
                target.write_text(contents)

            RELEASE.package(runtime, output, '1.3.0', 'https://cdn.interweb.bg')

            with zipfile.ZipFile(output / 'application.zip') as archive:
                entries = set(archive.namelist())
                self.assertEqual(entries, {
                    'artisan', 'config/game.php', 'public/build/manifest.json',
                    '.env.example', 'docker/.env.example', 'bootstrap/cache/.gitignore',
                })
            with zipfile.ZipFile(output / 'vendor.zip') as archive:
                self.assertEqual(set(archive.namelist()), {'vendor/autoload.php', 'vendor/composer/installed.json'})
            manifest = json.loads((output / 'manifest.json').read_text())
            self.assertEqual(manifest['version'], '1.3.0')
            self.assertEqual(manifest['compatibility']['managerMin'], '0.1.0')
            for artifact in ('application', 'vendor'):
                archive_bytes = (output / (artifact + '.zip')).read_bytes()
                self.assertEqual(manifest['artifacts'][artifact]['sha256'], hashlib.sha256(archive_bytes).hexdigest())
                self.assertEqual(manifest['artifacts'][artifact]['size'], len(archive_bytes))


if __name__ == '__main__':
    unittest.main()
