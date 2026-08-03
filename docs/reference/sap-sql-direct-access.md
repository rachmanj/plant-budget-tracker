# SAP B1 Direct SQL Server Access

## Date: 2025-11-13

## Overview

To overcome OData limitations (field name mismatches, UDFs not exposed, complex joins), we've implemented direct SQL Server access to execute the exact SQL query from `list_ITO.sql`.

## Configuration

Add these environment variables to your `.env` file:

```env
# SAP SQL Server Direct Access
SAP_SQL_HOST=arkasrv2
SAP_SQL_PORT=1433
SAP_SQL_DATABASE=your_sap_database_name
SAP_SQL_USERNAME=your_sql_username
SAP_SQL_PASSWORD=your_sql_password
```

**Note**: If not specified, it will fall back to using `SAP_SERVER_URL`, `SAP_DB_NAME`, `SAP_USER`, and `SAP_PASSWORD` from the existing SAP configuration.

## Database Connection

The connection is configured in `config/database.php` as `sap_sql`:

```php
'sap_sql' => [
    'driver' => 'sqlsrv',
    'host' => env('SAP_SQL_HOST', env('SAP_SERVER_URL')),
    'port' => env('SAP_SQL_PORT', '1433'),
    'database' => env('SAP_SQL_DATABASE', env('SAP_DB_NAME')),
    'username' => env('SAP_SQL_USERNAME', env('SAP_USER')),
    'password' => env('SAP_SQL_PASSWORD', env('SAP_PASSWORD')),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'options' => [
        'TrustServerCertificate' => true, // For development
    ],
],
```

## Implementation

### Service Method

`SapService::executeItoSqlQuery($startDate, $endDate)` executes the exact SQL query from `list_ITO.sql`:

- Uses parameterized queries for safety
- Executes on the `sap_sql` connection
- Returns results matching the SQL query structure exactly
- Includes all filters: `CreateDate`, `U_MIS_TransferType = 'OUT'`, warehouse join condition

### Sync Job Priority

The `SyncSapItoDocumentsJob` now tries methods in this order:

1. **SQL Server Direct Query** (new, most accurate)
2. OData Entity Query (fallback)
3. Query Execution via Service Layer (fallback)

## Benefits

✅ **100% Accuracy**: Matches SQL Query 5 results exactly  
✅ **All Filters Work**: `CreateDate`, `U_MIS_TransferType`, warehouse join  
✅ **Complete Data**: All fields from SQL query available  
✅ **No Field Mapping Issues**: Direct SQL field names  

## Requirements

- PHP `sqlsrv` extension installed
- Network access to SAP SQL Server
- SQL Server credentials with read access to `OWTR`, `WTR1`, `OITW`, etc.

## Testing

Test the SQL connection:

```bash
php artisan tinker
```

```php
DB::connection('sap_sql')->select('SELECT TOP 1 * FROM OWTR');
```

Test the sync:

```bash
php artisan sap:test-sync 2025-11-01 2025-11-12 --sync
```

For **production-style / scheduled** runs (same job as the web UI, with audit in `sap_logs`), use:

```bash
php artisan sap:sync-ito --today
php artisan sap:sync-ito --start=2026-03-01 --end=2026-03-31
```

See `**docs/SAP-ITO-SYNC-COMPLETE.md**` (2026-03-30 update) and `**docs/architecture.md**` (SAP ITO Sync Integration) for `trigger` / `triggered_by_user_id` / `synced_at` in `sap_logs.request_payload`.

## Security Considerations

- Use read-only SQL user if possible
- Store credentials securely in `.env` (never commit)
- Consider using encrypted connections in production
- Review SQL user permissions regularly

## Related Files

- `config/database.php` - Database connection configuration
- `app/Services/SapService.php` - `executeItoSqlQuery()` method
- `app/Jobs/SyncSapItoDocumentsJob.php` - Updated to use SQL first
- `database/list_ITO.sql` - Source SQL query

