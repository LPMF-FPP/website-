# Load Testing with k6

## Installation

```bash
# macOS
brew install k6

# Linux
sudo gpg -k
sudo gpg --no-default-keyring --keyring /usr/share/keyrings/k6-archive-keyring.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys C5AD17C747E3415A3642D57D77C6C491D6AC1D69
echo "deb [signed-by=/usr/share/keyrings/k6-archive-keyring.gpg] https://dl.k6.io/deb stable main" | sudo tee /etc/apt/sources.list.d/k6.list
sudo apt-get update
sudo apt-get install k6

# Windows
choco install k6
```

## Running Load Tests

```bash
# Run with default configuration
k6 run tests/Load/load-test.js

# Run with custom base URL
k6 run -e BASE_URL=https://your-domain.com tests/Load/load-test.js

# Run with custom VUs and duration
k6 run --vus 100 --duration 30s tests/Load/load-test.js

# Run and save results
k6 run --out json=results.json tests/Load/load-test.js
```

## Test Scenarios

The load test includes 4 key scenarios:

1. Homepage loading
2. Login authentication
3. Dashboard access
4. Requests listing

## Performance Thresholds

- 95% of requests must complete in under 500ms
- Error rate must stay below 10%
- Gradual ramp-up from 10 to 50 concurrent users

## Interpreting Results

Check these metrics after running:

- `http_req_duration`: Average response time
- `http_req_failed`: Failed request count
- `http_reqs`: Total requests
- `errors`: Custom error rate

## CI/CD Integration

Add to GitHub Actions:

```yaml
- name: Run Load Tests
  run: |
      k6 run tests/Load/load-test.js

- name: Upload Results
  uses: actions/upload-artifact@v4
  with:
      name: load-test-results
      path: load-test-results.json
```
