# Database Schema

The Make Santa Fe Membership Plugin creates and manages custom database tables to track member activities and sign-ins. This document outlines the database structure and relationships.

## Tables Overview

### Primary Table: `make_signin`

The main table for tracking member sign-ins and badge usage.

**Table Name**: `{wp_prefix}make_signin` (typically `wp_make_signin`)  
**Constant**: `SIGNIN_TABLENAME`

#### Schema Definition

```sql
CREATE TABLE make_signin (
  id INT NOT NULL AUTO_INCREMENT,
  time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
  badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  user INT NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Field Descriptions

| Field    | Type     | Description                             | Constraints                             |
| -------- | -------- | --------------------------------------- | --------------------------------------- |
| `id`     | INT      | Auto-incrementing primary key           | NOT NULL, AUTO_INCREMENT                |
| `time`   | DATETIME | Timestamp of member sign-in             | NOT NULL, DEFAULT '0000-00-00 00:00:00' |
| `badges` | TEXT     | JSON/serialized array of badge IDs used | NOT NULL, UTF8 encoding                 |
| `user`   | INT      | WordPress user ID of the member         | NOT NULL                                |

#### Indexes

- **Primary Key**: `id`
- **Recommended Indexes**:
  - `user` (for user-specific queries)
  - `time` (for date-range queries)
  - `user, time` (composite index for user activity over time)

## Data Relationships

### WordPress Integration

The plugin integrates with existing WordPress tables:

#### Users Table (`wp_users`)

- **Relationship**: `make_signin.user` → `wp_users.ID`
- **Type**: Many-to-One (many sign-ins per user)
- **Usage**: Links sign-in records to WordPress user accounts

#### Posts Table (`wp_posts`)

- **Relationship**: Badge IDs in `make_signin.badges` → `wp_posts.ID`
- **Type**: Many-to-Many (through serialized data)
- **Post Types**: `certs` (badges/certifications)
- **Usage**: Links sign-ins to specific badges/certifications

#### User Meta (`wp_usermeta`)

- **Usage**: Stores additional member information
- **Common Meta Keys**:
  - Member profile data
  - Badge certifications
  - Membership status

## Data Storage Patterns

### Badge Data Format

The `badges` field stores badge information as a serialized array or JSON:

```php
// Example badge data structure
$badges = [
    123, // Badge/certification post ID
    456, // Another badge post ID
    789  // Activity or equipment ID
];

// Stored as serialized PHP array or JSON
$stored_badges = serialize($badges);
// or
$stored_badges = json_encode($badges);
```

### Time Tracking

Sign-in times are stored in MySQL DATETIME format:

```sql
-- Example time values
'2024-01-15 14:30:00'  -- 2:30 PM on January 15, 2024
'2024-01-15 09:15:30'  -- 9:15:30 AM on January 15, 2024
```

## Query Patterns

### Common Queries

#### Recent Sign-ins

```sql
SELECT s.*, u.display_name, u.user_email
FROM make_signin s
JOIN wp_users u ON s.user = u.ID
WHERE s.time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY s.time DESC;
```

#### User Activity Summary

```sql
SELECT
    u.display_name,
    COUNT(s.id) as total_visits,
    MAX(s.time) as last_visit,
    MIN(s.time) as first_visit
FROM wp_users u
LEFT JOIN make_signin s ON u.ID = s.user
GROUP BY u.ID, u.display_name
ORDER BY total_visits DESC;
```

#### Badge Usage Statistics

```sql
SELECT
    s.badges,
    COUNT(*) as usage_count,
    COUNT(DISTINCT s.user) as unique_users
FROM make_signin s
WHERE s.time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY s.badges
ORDER BY usage_count DESC;
```

#### Daily Activity Report

```sql
SELECT
    DATE(s.time) as visit_date,
    COUNT(s.id) as total_signins,
    COUNT(DISTINCT s.user) as unique_members
FROM make_signin s
WHERE s.time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(s.time)
ORDER BY visit_date DESC;
```

## Data Migration and Maintenance

### Table Creation

The table is automatically created during plugin activation:

```php
function make_install() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE make_signin (
        id INT NOT NULL AUTO_INCREMENT,
        time datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        badges TEXT CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
        user INT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'make_install');
```

### Data Cleanup

#### Remove Old Records

```sql
-- Remove sign-ins older than 2 years
DELETE FROM make_signin
WHERE time < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

#### Archive Old Data

```sql
-- Create archive table
CREATE TABLE make_signin_archive LIKE make_signin;

-- Move old records to archive
INSERT INTO make_signin_archive
SELECT * FROM make_signin
WHERE time < DATE_SUB(NOW(), INTERVAL 1 YEAR);

-- Remove archived records from main table
DELETE FROM make_signin
WHERE time < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

## Performance Optimization

### Recommended Indexes

```sql
-- User-based queries
CREATE INDEX idx_user ON make_signin(user);

-- Time-based queries
CREATE INDEX idx_time ON make_signin(time);

-- Composite index for user activity over time
CREATE INDEX idx_user_time ON make_signin(user, time);

-- Recent activity queries
CREATE INDEX idx_time_user ON make_signin(time, user);
```

### Query Optimization Tips

1. **Use LIMIT for large datasets**:

   ```sql
   SELECT * FROM make_signin ORDER BY time DESC LIMIT 100;
   ```

2. **Use date ranges efficiently**:

   ```sql
   SELECT * FROM make_signin
   WHERE time BETWEEN '2024-01-01' AND '2024-01-31';
   ```

3. **Avoid SELECT \* in production**:
   ```sql
   SELECT id, time, user FROM make_signin WHERE user = 123;
   ```

## Backup and Recovery

### Backup Strategies

#### Full Table Backup

```sql
-- Create backup table
CREATE TABLE make_signin_backup_20240115 AS
SELECT * FROM make_signin;
```

#### Export Data

```bash
# MySQL dump
mysqldump -u username -p database_name make_signin > make_signin_backup.sql

# WordPress CLI
wp db export --tables=wp_make_signin
```

### Recovery Procedures

#### Restore from Backup

```sql
-- Drop current table (if corrupted)
DROP TABLE make_signin;

-- Restore from backup
CREATE TABLE make_signin AS
SELECT * FROM make_signin_backup_20240115;

-- Recreate indexes
ALTER TABLE make_signin ADD PRIMARY KEY (id);
CREATE INDEX idx_user ON make_signin(user);
CREATE INDEX idx_time ON make_signin(time);
```

## Data Privacy and Compliance

### GDPR Considerations

#### Data Retention

- Implement automatic cleanup of old records
- Provide data export functionality for users
- Enable data deletion upon user request

#### User Data Export

```php
function export_user_signin_data($user_id) {
    global $wpdb;

    $table_name = SIGNIN_TABLENAME;
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT time, badges FROM $table_name WHERE user = %d ORDER BY time DESC",
        $user_id
    ));

    return $results;
}
```

#### User Data Deletion

```php
function delete_user_signin_data($user_id) {
    global $wpdb;

    $table_name = SIGNIN_TABLENAME;
    $wpdb->delete($table_name, array('user' => $user_id), array('%d'));
}
```

## Monitoring and Analytics

### Health Checks

#### Table Integrity

```sql
-- Check for orphaned records (users that no longer exist)
SELECT s.* FROM make_signin s
LEFT JOIN wp_users u ON s.user = u.ID
WHERE u.ID IS NULL;

-- Check for invalid timestamps
SELECT * FROM make_signin
WHERE time = '0000-00-00 00:00:00' OR time IS NULL;

-- Check for empty badge data
SELECT * FROM make_signin
WHERE badges = '' OR badges IS NULL;
```

#### Performance Monitoring

```sql
-- Table size and row count
SELECT
    table_name,
    table_rows,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_name = 'make_signin';

-- Index usage analysis
SHOW INDEX FROM make_signin;
```

## Troubleshooting

### Common Issues

**Table not created during activation**:

- Check database user permissions
- Verify WordPress database connection
- Review error logs for specific issues

**Performance issues with large datasets**:

- Add appropriate indexes
- Implement data archiving
- Consider table partitioning for very large datasets

**Data corruption**:

- Regular backups are essential
- Monitor for invalid data entries
- Implement data validation in application code

## Next Steps

- [API Documentation](api/README.md)
- [Performance Monitoring](troubleshooting.md#performance)
- [Data Analytics](api/statistics.md)
- [Development Guidelines](development.md)
