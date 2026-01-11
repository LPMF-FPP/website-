// K6 Load Testing Script for LPMF LIMS
import http from "k6/http";
import { check, sleep } from "k6";
import { Rate } from "k6/metrics";

const errorRate = new Rate("errors");

export const options = {
    stages: [
        { duration: "2m", target: 10 }, // Ramp up to 10 users
        { duration: "5m", target: 10 }, // Stay at 10 users
        { duration: "2m", target: 50 }, // Ramp up to 50 users
        { duration: "5m", target: 50 }, // Stay at 50 users
        { duration: "2m", target: 0 }, // Ramp down to 0 users
    ],
    thresholds: {
        http_req_duration: ["p(95)<500"], // 95% of requests must complete below 500ms
        errors: ["rate<0.1"], // Error rate must be below 10%
    },
};

const BASE_URL = __ENV.BASE_URL || "http://localhost:8000";

export default function () {
    // Homepage load test
    let homeResponse = http.get(`${BASE_URL}/`);
    check(homeResponse, {
        "homepage status is 200": (r) => r.status === 200,
        "homepage loads in < 500ms": (r) => r.timings.duration < 500,
    }) || errorRate.add(1);

    sleep(1);

    // Login endpoint load test
    const loginPayload = JSON.stringify({
        email: "test@example.com",
        password: "password",
    });

    const loginParams = {
        headers: {
            "Content-Type": "application/json",
        },
    };

    let loginResponse = http.post(
        `${BASE_URL}/login`,
        loginPayload,
        loginParams,
    );
    check(loginResponse, {
        "login status is 200 or 302": (r) =>
            r.status === 200 || r.status === 302,
        "login completes in < 1s": (r) => r.timings.duration < 1000,
    }) || errorRate.add(1);

    sleep(1);

    // Dashboard load test (simulating authenticated request)
    let dashboardResponse = http.get(`${BASE_URL}/dashboard`);
    check(dashboardResponse, {
        "dashboard loads": (r) => r.status === 200 || r.status === 302,
        "dashboard loads in < 800ms": (r) => r.timings.duration < 800,
    }) || errorRate.add(1);

    sleep(2);

    // Requests list load test
    let requestsResponse = http.get(`${BASE_URL}/requests`);
    check(requestsResponse, {
        "requests page loads": (r) => r.status === 200 || r.status === 302,
        "requests page loads in < 1s": (r) => r.timings.duration < 1000,
    }) || errorRate.add(1);

    sleep(2);
}

export function handleSummary(data) {
    return {
        "load-test-results.json": JSON.stringify(data),
        stdout: textSummary(data, { indent: " ", enableColors: true }),
    };
}

function textSummary(data, options) {
    return `
===== Load Test Summary =====
Total Requests: ${data.metrics.http_reqs.values.count}
Failed Requests: ${data.metrics.http_req_failed.values.count}
Average Response Time: ${data.metrics.http_req_duration.values.avg.toFixed(2)}ms
95th Percentile: ${data.metrics.http_req_duration.values["p(95)"].toFixed(2)}ms
Error Rate: ${(data.metrics.errors.values.rate * 100).toFixed(2)}%
  `;
}
