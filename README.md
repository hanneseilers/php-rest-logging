# PHP REST Demo (logging + API keys + rate limits)

Dependency-free REST API in pure PHP.

## Structure

```
php-rest-logging/
  public/
    index.php
    .htaccess
  config/
    config.json
  storage/
    data.json
    ratelimit/
  logs/
    api.log
  .gitignore
  README.md
```

## Run (built-in server)

```bash
php -S localhost:8080 -t public
```

## Config

- `config/config.json` holds API keys, routes/scopes and rate limits.
- Default rate limits under `rate_limit.default`, per-key overrides under each key's `rate_limit`.

## Logging

- File: `logs/api.log`
- Format (single-line JSON per request):
  ```
  {"ts":"2025-01-01T12:00:00Z","ip":"203.0.113.5","method":"GET","path":"/items","status":200,"outcome":"ALLOW","key":"demo-key-123","reason":"OK"}
  {"ts":"2025-01-01T12:00:05Z","ip":"203.0.113.5","method":"POST","path":"/items","status":404,"outcome":"DENY","key":"(none)","reason":"NO_KEY"}
  {"ts":"2025-01-01T12:00:42Z","ip":"203.0.113.5","method":"GET","path":"/items/1","status":404,"outcome":"DENY","key":"readonly-abc","reason":"RATE_LIMIT"}
  ```
- Suitable for Fail2Ban custom filter (e.g., ban on repeated `"outcome":"DENY"`).

## Example cURL

```bash
# List
curl -i -H "X-API-Key: demo-key-123" http://localhost:8080/items

# Create
curl -i -H "X-API-Key: demo-key-123" -H "Content-Type: application/json"   -d '{"name":"Foo"}' http://localhost:8080/items

# Read one
curl -i -H "X-API-Key: readonly-abc" http://localhost:8080/items/1

# Update
curl -i -X PUT -H "X-API-Key: demo-key-123" -H "Content-Type: application/json"   -d '{"name":"Foo v2"}' http://localhost:8080/items/1
```
