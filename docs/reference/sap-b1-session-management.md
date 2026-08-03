# SAP B1 Service Layer Session Management

## Overview

SAP B1 Service Layer uses **cookie-based session management** for authentication. After successful login, SAP returns session cookies that must be included in all subsequent API requests. This document explains how session management is implemented in the Laravel application.

## How It Works

### 1. Cookie-Based Authentication

SAP B1 Service Layer does **NOT** use:

- ❌ Session ID in request headers
- ❌ Session ID in query parameters
- ❌ Bearer tokens

Instead, it uses:

- ✅ **HTTP Cookies** (standard web session management)
- ✅ Cookies are set by SAP after login via `Set-Cookie` headers
- ✅ Cookies must be sent back to SAP in the `Cookie` header for all subsequent requests

### 2. Implementation in Laravel

The application uses **Guzzle HTTP Client** with **CookieJar** to automatically manage cookies.

#### Setup

```php
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

// Create a CookieJar instance
$this->cookieJar = new CookieJar();

// Create Guzzle client with CookieJar
$this->client = new Client([
    'base_uri' => 'https://arkasrv2:50000/b1s/v1/',
    'cookies' => $this->cookieJar,  // ← Guzzle automatically manages cookies
    'headers' => ['Content-Type' => 'application/json'],
]);
```

**Key Point**: When you pass `'cookies' => $this->cookieJar` to the Guzzle Client, Guzzle will:

- Automatically store cookies from `Set-Cookie` headers in responses
- Automatically include cookies in the `Cookie` header for all requests
- Match cookies to the correct domain and path

### 3. Login Process

```php
public function login()
{
    $response = $this->client->post('Login', [
        'json' => [
            'CompanyDB' => $this->config['db_name'],
            'UserName' => $this->config['user'],
            'Password' => $this->config['password'],
        ],
    ]);

    // If successful, cookies are automatically stored in CookieJar
    return $response->getStatusCode() === 200;
}
```

**What Happens**:

1. **Request Sent**:
  ```
    POST https://arkasrv2:50000/b1s/v1/Login
    Content-Type: application/json

    {
      "CompanyDB": "your_database",
      "UserName": "your_user",
      "Password": "your_password"
    }
  ```
2. **SAP Response**:
  ```
    HTTP/1.1 200 OK
    Set-Cookie: B1SESSION=abc123def456...; Path=/; HttpOnly
    Set-Cookie: ROUTEID=.node1; Path=/
    Content-Type: application/json
  ```
3. **Guzzle Automatically**:
  - Extracts cookies from `Set-Cookie` headers
    - Stores them in the `CookieJar`
    - Associates them with the domain (`arkasrv2:50000`)

### 4. Subsequent Requests

After login, all subsequent requests automatically include the session cookies:

```php
// No manual cookie handling needed!
$response = $this->client->get('InventoryTransferRequests', [
    'query' => [
        '$filter' => "DocDate ge datetime'2025-11-01T00:00:00'"
    ]
]);
```

**What Guzzle Does Automatically**:

1. Checks `CookieJar` for cookies matching the request domain
2. Adds `Cookie` header to the request:
  ```
    GET https://arkasrv2:50000/b1s/v1/InventoryTransferRequests?$filter=...
    Cookie: B1SESSION=abc123def456...; ROUTEID=.node1
  ```
3. SAP validates the session from the cookies
4. Returns data if session is valid

## Visual Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│ STEP 1: Login Request                                       │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Client → SAP                                               │
│  POST /Login                                                │
│  { CompanyDB, UserName, Password }                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 2: SAP Response with Cookies                           │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SAP → Client                                               │
│  HTTP 200 OK                                                │
│  Set-Cookie: B1SESSION=abc123...                            │
│  Set-Cookie: ROUTEID=.node1                                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 3: Guzzle CookieJar Storage                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  CookieJar automatically stores:                            │
│  ┌─────────────────────────────────────┐                   │
│  │ B1SESSION = abc123def456...         │                   │
│  │ ROUTEID = .node1                    │                   │
│  │ Domain: arkasrv2:50000              │                   │
│  │ Path: /                             │                   │
│  └─────────────────────────────────────┘                   │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 4: Subsequent API Request                              │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Client → SAP                                               │
│  GET /InventoryTransferRequests?$filter=...                 │
│                                                             │
│  Guzzle automatically adds:                                 │
│  Cookie: B1SESSION=abc123...; ROUTEID=.node1               │
│                                                             │
└─────────────────────────────────────────────────────────────┘
                            ↓
┌─────────────────────────────────────────────────────────────┐
│ STEP 5: SAP Validates Session                               │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  SAP validates cookies → Returns data                       │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## Session Validation

### Checking if Session Exists

```php
// Check if we have cookies (session exists)
if (!$this->cookieJar->count()) {
    $this->login();  // No cookies, need to login
}
```

### Handling Session Expiration

SAP returns `401 Unauthorized` when the session expires. The code handles this automatically:

```php
try {
    $response = $this->client->get($entityName, [...]);
} catch (RequestException $e) {
    // If 401, session expired - re-login and retry
    if ($e->getResponse() && $e->getResponse()->getStatusCode() === 401) {
        $this->login();  // Get new session
        $response = $this->client->get($entityName, [...]);  // Retry
    }
}
```

## Inspecting Cookies (Debugging)

If you need to see what cookies are stored:

```php
// After login, inspect cookies
foreach ($this->cookieJar as $cookie) {
    echo $cookie->getName() . ' = ' . $cookie->getValue() . "\n";
    echo "Domain: " . $cookie->getDomain() . "\n";
    echo "Path: " . $cookie->getPath() . "\n";
    echo "Expires: " . ($cookie->getExpires() ? date('Y-m-d H:i:s', $cookie->getExpires()) : 'Session') . "\n";
    echo "---\n";
}
```

**Example Output**:

```
B1SESSION = abc123def456ghi789jkl012mno345pqr678stu901vwx234yz
Domain: arkasrv2
Path: /
Expires: 2025-11-13 02:31:35
---
ROUTEID = .node1
Domain: arkasrv2
Path: /
Expires: Session
---
```

## Key Points

### ✅ What Guzzle Does Automatically

1. **Stores cookies** from `Set-Cookie` headers in responses
2. **Includes cookies** in `Cookie` header for all requests
3. **Matches cookies** to the correct domain and path
4. **Handles cookie expiration** and removes expired cookies

### ❌ What You DON'T Need to Do

1. ❌ Manually extract session ID from login response
2. ❌ Manually add `Cookie` header to requests
3. ❌ Manually parse `Set-Cookie` headers
4. ❌ Manually manage cookie expiration

### ⚠️ Important Considerations

1. **CookieJar Scope**: The `CookieJar` is instance-specific. Each `SapService` instance has its own `CookieJar`, so sessions are isolated per instance.
2. **Session Lifetime**: SAP sessions typically expire after a period of inactivity. The code handles this with automatic re-login on 401 errors.
3. **Multiple Requests**: Once logged in, you can make multiple requests without re-logging in, as long as the session hasn't expired.
4. **Thread Safety**: If using the same `SapService` instance across multiple requests, the `CookieJar` is shared, so the session is maintained.

## Code Reference

**File**: `app/Services/SapService.php`

**Key Methods**:

- `__construct()`: Sets up Guzzle client with CookieJar
- `login()`: Authenticates and receives session cookies
- All other methods: Automatically use cookies from CookieJar

**Example Usage**:

```php
$sapService = app(SapService::class);

// Login (cookies stored automatically)
$sapService->login();

// Make requests (cookies sent automatically)
$results = $sapService->getStockTransferRequests('2025-11-01', '2025-11-10');
```

## Troubleshooting

### Problem: Getting 401 Unauthorized errors

**Solution**: Session expired. The code automatically re-logs in on 401 errors, but you can also manually check:

```php
if (!$this->cookieJar->count()) {
    $this->login();
}
```

### Problem: Cookies not being sent

**Check**:

1. Is `'cookies' => $this->cookieJar` set in the Client constructor?
2. Did login succeed? (Check `$this->cookieJar->count()` after login)
3. Are you using the same `SapService` instance? (Different instances have different CookieJars)

### Problem: Session expires too quickly

**Solution**: This is normal SAP behavior. The code handles it with automatic re-login. You can also implement session refresh logic if needed.

## Related Documentation

- [SAP B1 Service Layer Documentation](https://api.sap.com/api/B1SL/resource)
- [Guzzle HTTP Client Documentation](https://docs.guzzlephp.org/)
- [Guzzle CookieJar Documentation](https://docs.guzzlephp.org/en/stable/request-options.html#cookies)

## Multiple Applications / Concurrent Sessions

### Can Multiple Applications Use the Same Credentials?

**Yes**, multiple applications can use the same SAP B1 user credentials simultaneously. Each application/client gets its own independent session.

### How SAP Handles Multiple Sessions

```
Application 1 (Laravel)          Application 2 (Other App)
     │                                │
     ├─ Login → SAP                  ├─ Login → SAP
     │   ↓                           │   ↓
     │   Session A                   │   Session B
     │   (B1SESSION=abc123...)       │   (B1SESSION=xyz789...)
     │                                │
     └─ Requests with Session A      └─ Requests with Session B
```

**Key Points**:

- ✅ Each application gets its **own session** (different cookies)
- ✅ Sessions are **independent** - they don't interfere with each other
- ✅ Both can make requests **simultaneously**
- ✅ Each session has its own **transaction context**

### Potential Conflicts and Issues

#### 1. **Session Limits** ⚠️

SAP B1 may have limits on concurrent sessions per user:

- **Default**: Usually 5-10 concurrent sessions per user
- **Impact**: If too many applications are logged in, new logins might fail
- **Solution**: Monitor session count, implement session pooling if needed

#### 2. **Data Conflicts** ⚠️

**Scenario**: Two applications try to modify the same record simultaneously

```
Application 1: Update Invoice #123 → Status = "Posted"
Application 2: Update Invoice #123 → Status = "Cancelled"
```

**What Happens**:

- Last write wins (standard database behavior)
- No automatic conflict resolution
- Potential data inconsistency

**Best Practices**:

- Use optimistic locking (check `UpdateDate` before updating)
- Implement proper transaction management
- Use SAP's built-in locking mechanisms if available

#### 3. **Transaction Conflicts** ⚠️

**Scenario**: Two applications try to create invoices with the same document number

```
Application 1: Create Invoice → DocNum = "INV-001"
Application 2: Create Invoice → DocNum = "INV-001" (if using manual numbering)
```

**What Happens**:

- SAP will reject the second request with a duplicate error
- One application succeeds, one fails

**Solution**: Use SAP's automatic numbering or implement proper sequence management

#### 4. **Performance Impact** ⚠️

**Multiple concurrent sessions**:

- ✅ Can improve throughput (parallel processing)
- ⚠️ May increase database load
- ⚠️ May cause resource contention

### Current Implementation Behavior

In the Laravel application:

```php
// Each request creates a NEW SapService instance
$sapService = app(SapService::class);
```

**What This Means**:

- Each HTTP request gets a **new SapService instance** (unless registered as singleton)
- Each instance has its **own CookieJar**
- Each instance creates its **own SAP session** (if not shared)

**Potential Issue**: If you make multiple requests in the same HTTP request lifecycle, each might create a new SAP session, leading to:

- Multiple sessions per user
- Session limit exhaustion
- Unnecessary overhead

### Best Practices

#### 1. **Session Reuse** (Recommended)

Use Laravel's service container to share the same `SapService` instance:

```php
// In AppServiceProvider
public function register()
{
    $this->app->singleton(SapService::class, function ($app) {
        return new SapService();
    });
}
```

**Benefits**:

- ✅ One session per application instance
- ✅ Reduced session count
- ✅ Better performance (reuses connection)

#### 2. **Session Pooling** (For High Traffic)

If you have many concurrent requests, implement session pooling:

```php
class SapServicePool
{
    protected $pool = [];
    protected $maxSessions = 5;

    public function getSession()
    {
        // Reuse existing session if available
        foreach ($this->pool as $service) {
            if ($service->isValid()) {
                return $service;
            }
        }

        // Create new session if under limit
        if (count($this->pool) < $this->maxSessions) {
            $service = new SapService();
            $service->login();
            $this->pool[] = $service;
            return $service;
        }

        // Wait or throw error if pool is full
        throw new \Exception('Session pool exhausted');
    }
}
```

#### 3. **Monitor Session Count**

Track how many sessions are active:

```php
// Check SAP session count (if SAP provides this endpoint)
$sessions = $sapService->getActiveSessions();

if ($sessions > 10) {
    Log::warning("High session count: {$sessions}");
}
```

#### 4. **Implement Proper Error Handling**

Handle session limit errors gracefully:

```php
try {
    $sapService->login();
} catch (\Exception $e) {
    if (str_contains($e->getMessage(), 'session limit')) {
        // Wait and retry, or use existing session
        sleep(5);
        return $this->retryWithExistingSession();
    }
    throw $e;
}
```

### Recommendations for Your Application

#### Current State

Your current implementation creates a new `SapService` instance per request (unless registered as singleton), which means:

- Each sync operation might create a new SAP session
- Sessions are independent and don't conflict
- But you might hit session limits with high traffic

#### Recommended Improvements

1. **Make SapService a Singleton** (Simple fix):

```php
// In AppServiceProvider
public function register()
{
    $this->app->singleton(SapService::class);
}
```

1. **Add Session Validation**:

```php
// In SapService
public function hasValidSession()
{
    return $this->cookieJar->count() > 0;
}

public function ensureSession()
{
    if (!$this->hasValidSession()) {
        $this->login();
    }
}
```

1. **Implement Session Reuse**:

```php
// In controller
public function sapSyncIto(Request $request)
{
    $sapService = app(SapService::class); // Reuses same instance if singleton
    $sapService->ensureSession(); // Only login if needed
    // ... rest of code
}
```

### Testing Concurrent Access

To test if multiple applications cause conflicts:

```php
// Test script
$sapService1 = new SapService();
$sapService2 = new SapService();

// Both login with same credentials
$sapService1->login();
$sapService2->login();

// Make simultaneous requests
$results1 = $sapService1->getStockTransferRequests('2025-11-01', '2025-11-10');
$results2 = $sapService2->getStockTransferRequests('2025-11-01', '2025-11-10');

// Both should work independently
```

### Summary Table

**Multiple Applications with Same Credentials**:


| Aspect                  | Behavior                      | Risk Level |
| ----------------------- | ----------------------------- | ---------- |
| **Session Creation**    | Each app gets own session     | ✅ Low      |
| **Concurrent Requests** | Allowed, independent          | ✅ Low      |
| **Data Conflicts**      | Last write wins               | ⚠️ Medium  |
| **Session Limits**      | May hit limits with many apps | ⚠️ Medium  |
| **Performance**         | Can improve throughput        | ✅ Low      |


**Best Practices**:

1. ✅ Use singleton pattern for `SapService` to reuse sessions
2. ✅ Monitor session count
3. ✅ Implement proper error handling for session limits
4. ✅ Use optimistic locking for data modifications
5. ✅ Coordinate with other applications if modifying same data

## Summary

**SAP B1 Service Layer session management is handled automatically by Guzzle's CookieJar**. You don't need to manually manage session IDs or cookies. Simply:

1. ✅ Create a `CookieJar` and pass it to the Guzzle Client
2. ✅ Call `login()` to authenticate
3. ✅ Make API requests - cookies are sent automatically
4. ✅ Handle 401 errors by re-logging in

**Multiple Applications**:

- ✅ Can use same credentials simultaneously
- ✅ Each gets its own independent session
- ⚠️ Watch for session limits and data conflicts
- ✅ Implement session reuse for better performance

The session is maintained through HTTP cookies, and Guzzle handles all the complexity for you.