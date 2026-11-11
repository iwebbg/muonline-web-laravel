# Public Blade themes

`muonline/` is the complete default public theme. Add a direct child directory
whose slug matches `^[a-z0-9][a-z0-9-]*$` and copy only the Blade files that the
custom theme overrides. Missing files fall back to `muonline/`.

Administration, mail, error and pagination views remain under the core
`resources/views` tree. See [`docs/themes.md`](../../../docs/themes.md) for the
full view, asset, update and cache contract.
