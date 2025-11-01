# NEXT STEP: Database Migration - MySQL to PostgreSQL

## Overview

This document provides detailed, actionable instructions for Step 1 of the Svelte + PostgreSQL migration plan: converting the existing MySQL database schema to PostgreSQL and setting up the development environment.

**Timeline**: 5 days (Week 1)  
**Prerequisites**: Access to `ibl5/schema.sql`, Docker installed, basic SQL knowledge  
**Goal**: PostgreSQL schema ready for development, test migration successful

---

## Day 1-2: Analyze MySQL Schema and PostgreSQL Incompatibilities

### Task 1.1: Review Current Schema

```bash
# Review the complete schema
cd /home/runner/work/IBL5/IBL5
wc -l ibl5/schema.sql  # Understand size
head -100 ibl5/schema.sql  # Review header and structure
```

**Create a schema analysis document**: `IBL6/docs/schema-analysis.md`

Document the following:

1. **Table Count**: Total tables, breakdown by prefix (`ibl_*`, `nuke_*`, Laravel tables)
2. **Storage Engines**: Count of MyISAM vs InnoDB tables
3. **Character Sets**: latin1 vs utf8mb4 tables
4. **Key Entities**: Core tables for migration priority
5. **Relationships**: Document logical foreign key relationships (many missing in current schema)

### Task 1.2: Identify PostgreSQL Incompatibilities

Create: `IBL6/docs/mysql-to-postgres-mapping.md`

#### Data Type Mappings

| MySQL Type | PostgreSQL Type | Notes |
|------------|----------------|-------|
| `TINYINT(1)` | `BOOLEAN` | For true/false values |
| `TINYINT` | `SMALLINT` | For small integers |
| `MEDIUMINT` | `INTEGER` | PostgreSQL doesn't have MEDIUMINT |
| `INT` | `INTEGER` | Exact match |
| `BIGINT` | `BIGINT` | Exact match |
| `AUTO_INCREMENT` | `SERIAL` or `GENERATED ALWAYS AS IDENTITY` | Prefer IDENTITY |
| `FLOAT` | `REAL` | Single precision |
| `DOUBLE` | `DOUBLE PRECISION` | Double precision |
| `DECIMAL(M,D)` | `NUMERIC(M,D)` | Exact match |
| `VARCHAR(N)` | `VARCHAR(N)` | Exact match |
| `TEXT` | `TEXT` | Exact match |
| `BLOB` | `BYTEA` | Binary data |
| `DATE` | `DATE` | Note: no zero dates in PostgreSQL |
| `DATETIME` | `TIMESTAMP` | Without timezone |
| `TIMESTAMP` | `TIMESTAMP WITH TIME ZONE` | With timezone recommended |
| `ENUM('a','b')` | `CHECK (col IN ('a','b'))` | Or use a lookup table |
| `SET` | `TEXT[]` | Array type |

#### Special Cases

1. **Zero Dates**: `'0000-00-00'` → `NULL`
   - MySQL allows zero dates, PostgreSQL does not
   - Find all zero dates: `grep "0000-00-00" ibl5/schema.sql`
   - Replace with `NULL` or valid default date

2. **Unsigned Integers**: PostgreSQL doesn't have UNSIGNED
   - Convert to next larger type or use CHECK constraints
   - Example: `UNSIGNED INT` → `BIGINT` or `INTEGER CHECK (col >= 0)`

3. **Case Sensitivity**: PostgreSQL identifiers are case-insensitive unless quoted
   - Convert all table/column names to lowercase
   - Use snake_case consistently

4. **Boolean Values**: 
   - MySQL stores as TINYINT(1) with 0/1
   - PostgreSQL has native BOOLEAN type
   - Update application code to use true/false instead of 0/1

5. **Auto-increment**: 
   ```sql
   -- MySQL
   id INT AUTO_INCREMENT PRIMARY KEY
   
   -- PostgreSQL (modern way)
   id INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY
   ```

### Task 1.3: Create Incompatibility Report

Run this analysis on the schema:

```bash
# Count data type usage
grep -o "TINYINT\|MEDIUMINT\|AUTO_INCREMENT\|ENUM\|SET" ibl5/schema.sql | sort | uniq -c

# Find zero dates
grep -c "0000-00-00" ibl5/schema.sql

# Find storage engine declarations
grep -c "ENGINE=MyISAM\|ENGINE=InnoDB" ibl5/schema.sql
```

Create: `IBL6/docs/incompatibility-report.txt` with findings and remediation plan.

---

## Day 3-4: Create PostgreSQL Prisma Schema

### Task 2.1: Set Up Prisma for PostgreSQL

```bash
cd IBL6

# Update package.json if needed
npm install @prisma/client@latest prisma@latest

# Create new PostgreSQL schema file
cp prisma/schema.prisma prisma/schema-postgres.prisma
```

### Task 2.2: Update Datasource Configuration

Edit `IBL6/prisma/schema-postgres.prisma`:

```prisma
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "postgresql"  // Changed from "mysql"
  url      = env("DATABASE_URL")
}
```

### Task 2.3: Convert Core Models

#### Team Model (Example Conversion)

**Before (MySQL)**:
```prisma
model Team {
  teamid         Int     @id @default(0)
  city           String  @default("") @map("team_city") @db.VarChar(24)
  name           String  @default("") @map("team_name") @db.VarChar(16)
  // ... more fields
}
```

**After (PostgreSQL)**:
```prisma
model Team {
  teamid         Int     @id @default(autoincrement())  // Changed: use autoincrement
  city           String  @default("") @map("team_city") @db.VarChar(24)
  name           String  @default("") @map("team_name") @db.VarChar(16)
  // ... more fields
  
  // Add proper indexes
  @@index([name])
  @@map("ibl_team_info")
}
```

#### IblPlayer Model Conversions

Key changes:
1. Remove `@db.UnsignedBigInt` (use `@db.BigInt` instead)
2. Change `@db.SmallInt` → keep as-is (PostgreSQL has SMALLINT)
3. Update date fields to handle NULL instead of zero dates
4. Convert TINYINT(1) to Boolean where applicable

### Task 2.4: Add Missing Foreign Keys

The current schema has logical relationships but no foreign key constraints (MyISAM limitation).

Add these relationships:

```prisma
model IblPlayer {
  // ... existing fields
  
  // Add foreign key relationships
  teamId         Int?    @map("tid")
  currentTeam    Team?   @relation("currentTeamPlayers", fields: [teamId], references: [teamid])
  
  boxScores      BoxPlayer[]
  
  @@index([teamId])
  @@map("ibl_plr")
}

model BoxPlayer {
  // ... existing fields
  
  pid       Int
  player    IblPlayer @relation(fields: [pid], references: [pid])
  
  // Add team relationships if not already present
  awayTeamId Int?     @map("visitorTID")
  homeTeamId Int?     @map("homeTID")
  awayTeam   Team?    @relation("awayTeamPlayers", fields: [awayTeamId], references: [teamid])
  homeTeam   Team?    @relation("homeTeamPlayers", fields: [homeTeamId], references: [teamid])
  
  @@id([gameDate, pid])
  @@map("ibl_box_scores")
}
```

### Task 2.5: Validate Prisma Schema

```bash
cd IBL6

# Format the schema
npx prisma format

# Validate syntax
npx prisma validate

# Generate client to check for errors
npx prisma generate
```

Fix any validation errors before proceeding.

---

## Day 5: Create Migration Scripts and Docker Setup

### Task 3.1: Set Up Docker Compose for Local Development

Create: `IBL6/docker-compose.yml`

```yaml
version: '3.8'

services:
  postgres:
    image: postgres:15-alpine
    container_name: ibl6_postgres
    environment:
      POSTGRES_DB: ibl6_dev
      POSTGRES_USER: ibl_user
      POSTGRES_PASSWORD: dev_password
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./migrations:/docker-entrypoint-initdb.d
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ibl_user -d ibl6_dev"]
      interval: 10s
      timeout: 5s
      retries: 5

  # Optional: pgAdmin for database management
  pgadmin:
    image: dpage/pgadmin4:latest
    container_name: ibl6_pgadmin
    environment:
      PGADMIN_DEFAULT_EMAIL: admin@ibl.local
      PGADMIN_DEFAULT_PASSWORD: admin
    ports:
      - "5050:80"
    depends_on:
      - postgres

volumes:
  postgres_data:
```

Create: `IBL6/.env.local`

```bash
# PostgreSQL connection string
DATABASE_URL="postgresql://ibl_user:dev_password@localhost:5432/ibl6_dev"

# For production (template)
# DATABASE_URL="postgresql://user:password@host:5432/database?schema=public"
```

Update: `IBL6/.gitignore`

```
# Environment files
.env
.env.local
.env.production
```

### Task 3.2: Create Database Migration Script

Create: `IBL6/migrations/mysql-to-postgres/export-from-mysql.sh`

```bash
#!/bin/bash
# Export data from MySQL database

set -e

MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD}"
MYSQL_DATABASE="${MYSQL_DATABASE:-ibl5}"
OUTPUT_DIR="./output"

echo "Exporting data from MySQL..."

mkdir -p "$OUTPUT_DIR"

# Export schema structure (we'll rewrite this, but good for reference)
mysqldump -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
  --no-data \
  --skip-add-drop-table \
  "$MYSQL_DATABASE" > "$OUTPUT_DIR/schema.sql"

# Export data only (no CREATE TABLE statements)
# Export tables in batches to avoid memory issues
TABLES=$(mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
  -D "$MYSQL_DATABASE" -N -e "SHOW TABLES")

for TABLE in $TABLES; do
  echo "Exporting table: $TABLE"
  
  # Export as CSV for easier data transformation
  mysql -h "$MYSQL_HOST" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" \
    -D "$MYSQL_DATABASE" \
    -e "SELECT * FROM $TABLE" \
    | sed 's/\t/,/g' \
    > "$OUTPUT_DIR/${TABLE}.csv"
done

echo "Export complete! Data saved to $OUTPUT_DIR/"
```

Create: `IBL6/migrations/mysql-to-postgres/transform-data.py`

```python
#!/usr/bin/env python3
"""
Transform MySQL data to PostgreSQL-compatible format
"""

import csv
import sys
from pathlib import Path
from datetime import datetime

def transform_date(value):
    """Convert MySQL zero dates to NULL"""
    if value in ['0000-00-00', '0000-00-00 00:00:00']:
        return None
    return value

def transform_boolean(value):
    """Convert TINYINT(1) to boolean"""
    if value in ['0', '1']:
        return 'false' if value == '0' else 'true'
    return value

def transform_csv(input_file, output_file, transformations):
    """Apply transformations to CSV file"""
    with open(input_file, 'r') as infile, open(output_file, 'w', newline='') as outfile:
        reader = csv.reader(infile)
        writer = csv.writer(outfile)
        
        # Copy header
        header = next(reader)
        writer.writerow(header)
        
        # Transform data rows
        for row in reader:
            transformed_row = []
            for i, value in enumerate(row):
                col_name = header[i]
                
                # Apply column-specific transformations
                if col_name in transformations.get('dates', []):
                    value = transform_date(value)
                elif col_name in transformations.get('booleans', []):
                    value = transform_boolean(value)
                
                transformed_row.append(value)
            
            writer.writerow(transformed_row)

def main():
    input_dir = Path('./output')
    output_dir = Path('./output/transformed')
    output_dir.mkdir(exist_ok=True)
    
    # Define transformations per table
    table_transformations = {
        'ibl_plr': {
            'dates': ['droptime'],
            'booleans': ['active', 'dc_active', 'injured', 'retired']
        },
        'ibl_team_info': {
            'booleans': ['hasMLE', 'hasLLE', 'usedExtChunk', 'usedExtSeason']
        },
        # Add more tables as needed
    }
    
    # Transform all CSV files
    for csv_file in input_dir.glob('*.csv'):
        table_name = csv_file.stem
        print(f"Transforming {table_name}...")
        
        transformations = table_transformations.get(table_name, {})
        output_file = output_dir / csv_file.name
        
        transform_csv(csv_file, output_file, transformations)
    
    print(f"Transformation complete! Files saved to {output_dir}/")

if __name__ == '__main__':
    main()
```

Create: `IBL6/migrations/mysql-to-postgres/import-to-postgres.sh`

```bash
#!/bin/bash
# Import transformed data into PostgreSQL

set -e

POSTGRES_HOST="${POSTGRES_HOST:-localhost}"
POSTGRES_PORT="${POSTGRES_PORT:-5432}"
POSTGRES_USER="${POSTGRES_USER:-ibl_user}"
POSTGRES_PASSWORD="${POSTGRES_PASSWORD:-dev_password}"
POSTGRES_DATABASE="${POSTGRES_DATABASE:-ibl6_dev}"
INPUT_DIR="./output/transformed"

echo "Creating schema in PostgreSQL..."

# Apply Prisma schema
cd ..
npx prisma migrate dev --name init --schema=./prisma/schema-postgres.prisma

echo "Importing data into PostgreSQL..."

# Import CSV files using COPY command
for CSV_FILE in "$INPUT_DIR"/*.csv; do
  TABLE_NAME=$(basename "$CSV_FILE" .csv)
  echo "Importing table: $TABLE_NAME"
  
  PGPASSWORD="$POSTGRES_PASSWORD" psql \
    -h "$POSTGRES_HOST" \
    -p "$POSTGRES_PORT" \
    -U "$POSTGRES_USER" \
    -d "$POSTGRES_DATABASE" \
    -c "\COPY $TABLE_NAME FROM '$CSV_FILE' WITH CSV HEADER"
done

echo "Import complete!"
```

### Task 3.3: Create Data Validation Script

Create: `IBL6/migrations/mysql-to-postgres/validate-migration.sql`

```sql
-- Validation queries to compare MySQL vs PostgreSQL data

-- Row counts comparison
SELECT 'ibl_team_info' as table_name, COUNT(*) as row_count FROM ibl_team_info
UNION ALL
SELECT 'ibl_plr' as table_name, COUNT(*) as row_count FROM ibl_plr
UNION ALL
SELECT 'ibl_box_scores' as table_name, COUNT(*) as row_count FROM ibl_box_scores
UNION ALL
SELECT 'ibl_box_scores_teams' as table_name, COUNT(*) as row_count FROM ibl_box_scores_teams;

-- Sample data verification
SELECT * FROM ibl_team_info ORDER BY teamid LIMIT 5;
SELECT * FROM ibl_plr ORDER BY pid LIMIT 5;

-- Check for NULL values in key fields
SELECT 
  COUNT(*) FILTER (WHERE teamid IS NULL) as null_teamids,
  COUNT(*) FILTER (WHERE city IS NULL) as null_cities,
  COUNT(*) FILTER (WHERE name IS NULL) as null_names
FROM ibl_team_info;

-- Verify foreign key relationships
SELECT 
  p.pid,
  p.name as player_name,
  p.teamId,
  t.name as team_name
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.teamId = t.teamid
WHERE p.teamId IS NOT NULL
LIMIT 10;

-- Check for data anomalies
SELECT pid, name, age FROM ibl_plr WHERE age NOT IN ('00', '01', '02', '03', '04', '05', '06', '07', '08', '09', '10') LIMIT 10;
```

### Task 3.4: Create Migration Documentation

Create: `IBL6/migrations/README.md`

```markdown
# Database Migration Guide

## Prerequisites

- Docker and Docker Compose installed
- Access to MySQL production database (for export)
- Python 3.8+ (for data transformation scripts)

## Step-by-Step Migration Process

### 1. Start PostgreSQL with Docker

```bash
cd IBL6
docker-compose up -d postgres
```

Verify PostgreSQL is running:
```bash
docker-compose ps
```

### 2. Export Data from MySQL

```bash
cd migrations/mysql-to-postgres

# Set MySQL credentials
export MYSQL_HOST=your_mysql_host
export MYSQL_USER=your_mysql_user
export MYSQL_PASSWORD=your_mysql_password
export MYSQL_DATABASE=ibl5

# Run export
./export-from-mysql.sh
```

### 3. Transform Data

```bash
# Transform data to PostgreSQL format
python3 transform-data.py
```

### 4. Create PostgreSQL Schema

```bash
cd ../..
npx prisma migrate dev --name init --schema=./prisma/schema-postgres.prisma
```

### 5. Import Data

```bash
cd migrations/mysql-to-postgres
./import-to-postgres.sh
```

### 6. Validate Migration

```bash
# Connect to PostgreSQL
docker exec -it ibl6_postgres psql -U ibl_user -d ibl6_dev

# Run validation queries
\i validate-migration.sql
```

### 7. Compare Results

Compare row counts and sample data between MySQL and PostgreSQL.

Document any discrepancies in `migration-report.md`.

## Rollback Procedure

If migration fails:

1. Stop PostgreSQL: `docker-compose down`
2. Remove volume: `docker volume rm ibl6_postgres_data`
3. Restart: `docker-compose up -d`
4. Fix issues and retry migration

## Troubleshooting

### Connection Refused
- Check Docker container status: `docker-compose ps`
- Check logs: `docker-compose logs postgres`

### Import Errors
- Check CSV encoding (should be UTF-8)
- Verify table schema matches data
- Check for NULL constraint violations

### Performance Issues
- Disable indexes during bulk import
- Use COPY instead of INSERT
- Increase PostgreSQL memory settings
```

---

## Testing & Validation Checklist

After completing the migration, verify:

- [ ] PostgreSQL running in Docker
- [ ] Prisma schema validates without errors
- [ ] Database schema created successfully
- [ ] All tables imported with correct row counts
- [ ] Sample queries return expected results
- [ ] Foreign key relationships work correctly
- [ ] No constraint violations
- [ ] Character encoding correct (UTF-8)
- [ ] Date fields handled properly (no zero dates)
- [ ] Boolean fields converted correctly
- [ ] Application can connect to PostgreSQL
- [ ] Prisma Client generates successfully

---

## Next Steps

Once database migration is complete, proceed to:

**Step 2: API Layer Foundation** (Week 2-3)
- Set up SvelteKit server routes
- Implement Prisma client in API endpoints
- Build core CRUD operations
- Create authentication system

See `SVELTE_POSTGRESQL_MIGRATION_PLAN.md` for full roadmap.

---

## Resources

- **Prisma PostgreSQL Documentation**: https://www.prisma.io/docs/concepts/database-connectors/postgresql
- **PostgreSQL Data Types**: https://www.postgresql.org/docs/current/datatype.html
- **MySQL to PostgreSQL Migration Guide**: https://wiki.postgresql.org/wiki/Converting_from_other_Databases_to_PostgreSQL
- **Docker Compose PostgreSQL**: https://hub.docker.com/_/postgres

---

## Troubleshooting Common Issues

### Issue: "relation does not exist"
**Solution**: Run Prisma migrations to create tables
```bash
npx prisma migrate dev
```

### Issue: "column does not exist"
**Solution**: Check Prisma schema mapping with `@map()` annotations

### Issue: Zero dates cause errors
**Solution**: Update transformation script to convert to NULL

### Issue: Integer overflow
**Solution**: Use BIGINT instead of INTEGER for large numbers

### Issue: Foreign key constraint fails
**Solution**: Ensure referenced records exist before creating relationships

---

## Support

For questions or issues:
1. Check this documentation first
2. Review Prisma documentation
3. Check PostgreSQL logs: `docker-compose logs postgres`
4. Consult the team on Discord
