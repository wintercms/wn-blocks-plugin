# Changelog

## Unreleased

### Performance
- **Cross-request config cache** — `BlockManager::getConfigs()` now stores
  built block configs in the Laravel cache (default: file/database driver),
  keyed by an md5 signature of every block file's mtime. On warm requests the
  full config build (~17–97ms for ~100 blocks) is replaced by a cheap mtime
  check (~0.1ms). The cache self-invalidates when any `.block` file changes,
  so no manual `php artisan cache:clear` is needed after editing blocks. A
  per-request in-memory memo prevents even the mtime check from running more
  than once per request.
