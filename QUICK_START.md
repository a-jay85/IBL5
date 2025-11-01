# Quick Start: Begin IBL6 Migration

**Goal**: Get your development environment ready and start Week 1 tasks  
**Time Required**: ~4 hours for setup, then 3 days for first milestone

---

## Prerequisites Checklist

Before starting, ensure you have:

- [ ] Git access to this repository
- [ ] Node.js 18+ installed (`node --version`)
- [ ] npm installed (`npm --version`)
- [ ] Code editor (VS Code recommended)
- [ ] Terminal access
- [ ] 20GB free disk space

---

## Step 1: Local Environment Setup (30 minutes)

### Clone and Navigate

```bash
# If not already cloned
git clone https://github.com/a-jay85/IBL5.git
cd IBL5/IBL6
```

### Install Dependencies

```bash
# Install Node.js dependencies
npm install

# This will:
# - Install SvelteKit, Vite, TypeScript
# - Install Prisma and Prisma Client
# - Install TailwindCSS and DaisyUI
# - Install testing frameworks (Vitest, Playwright)
# - Run prisma generate automatically (postinstall hook)
```

**Expected output**: `added XXX packages` (should complete in 1-2 minutes)

### Verify Installation

```bash
# Check that basic commands work
npm run check      # Type-check the project
npm run format     # Format code with Prettier
npm run lint       # Lint with ESLint
```

---

## Step 2: Install PostgreSQL (1 hour)

### Option A: macOS (with Homebrew)

```bash
# Install PostgreSQL
brew install postgresql@15

# Start PostgreSQL service
brew services start postgresql@15

# Create database
createdb ibl6_dev

# Verify installation
psql -d ibl6_dev -c "SELECT version();"
```

### Option B: Ubuntu/Debian Linux

```bash
# Install PostgreSQL
sudo apt-get update
sudo apt-get install postgresql-15 postgresql-contrib

# Start PostgreSQL service
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Switch to postgres user and create database
sudo -u postgres createdb ibl6_dev
sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD 'your_password';"

# Verify installation
psql -U postgres -d ibl6_dev -c "SELECT version();"
```

### Option C: Windows

1. Download PostgreSQL 15 installer from https://www.postgresql.org/download/windows/
2. Run installer, follow wizard (remember password you set!)
3. Open SQL Shell (psql) from Start Menu
4. Press Enter for defaults, then enter your password
5. Create database:
   ```sql
   CREATE DATABASE ibl6_dev;
   \q
   ```

### Option D: Docker (Any OS)

```bash
# Start PostgreSQL in Docker
docker run --name ibl-postgres \
  -e POSTGRES_DB=ibl6_dev \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD=postgres \
  -p 5432:5432 \
  -d postgres:15

# Verify
docker exec -it ibl-postgres psql -U postgres -d ibl6_dev -c "SELECT version();"
```

---

## Step 3: Configure Database Connection (5 minutes)

### Create Environment File

```bash
# In IBL6/ directory
cp .env.example .env  # If .env.example exists, otherwise:
touch .env
```

### Edit .env File

```bash
# IBL6/.env
DATABASE_URL="postgresql://postgres:your_password@localhost:5432/ibl6_dev"

# For Docker users:
# DATABASE_URL="postgresql://postgres:postgres@localhost:5432/ibl6_dev"
```

**Important**: Replace `your_password` with your actual PostgreSQL password!

---

## Step 4: Update Prisma Configuration (5 minutes)

### Edit prisma/schema.prisma

```typescript
// Change line 6 from:
provider = "mysql"

// To:
provider = "postgresql"
```

### The change should look like this:

```typescript
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "postgresql"  // ← Changed from "mysql"
  url      = env("DATABASE_URL")
}

// Rest of the schema stays the same...
```

---

## Step 5: Test the Setup (10 minutes)

### Regenerate Prisma Client

```bash
npx prisma generate
```

**Expected output**: "Generated Prisma Client..."

### Create First Migration

```bash
npx prisma migrate dev --name init
```

**Expected output**: 
- Creates migration files
- Applies migration to database
- Shows "Your database is now in sync with your schema"

### Open Prisma Studio (Visual Database Browser)

```bash
npx prisma studio
```

**Expected**: Browser opens to http://localhost:5555 showing your database tables

### Test Development Server

```bash
npm run dev
```

**Expected**: 
- Server starts on http://localhost:5173
- Opens browser (or navigate manually)
- See IBL6 app (even if minimal)

**Ctrl+C to stop the server**

---

## Step 6: Verify Everything Works (15 minutes)

### Run Test Suite

```bash
# Unit tests
npm run test:unit

# Expected: Tests pass (or skip if none written yet)
```

### Check Code Quality

```bash
# Type checking
npm run check

# Linting
npm run lint

# Formatting
npm run format
```

**All should pass with no errors**

---

## Step 7: Start Week 1 Work (3 days)

Now you're ready for the main tasks! Follow the detailed guide in `MIGRATION_PLAN.md` under "IMMEDIATE NEXT STEP".

### Quick Reference: Week 1 Tasks

#### Day 1 (6 hours)
- [ ] Map statistics tables (12 tables)
- [ ] Map game/schedule tables (5 tables)
- [ ] Test Prisma schema compiles

#### Day 2 (6 hours)
- [ ] Map transaction tables (8 tables)
- [ ] Map voting/awards tables (4 tables)
- [ ] Map historical tables (3 tables)
- [ ] Run `npx prisma generate` and fix any errors

#### Day 3 (6 hours)
- [ ] Map PHP-Nuke tables (10 tables)
- [ ] Map miscellaneous tables (20 tables)
- [ ] Create seed script with sample data
- [ ] Run `npx prisma db seed` and verify

**See `MIGRATION_PLAN.md` "Step 1.3" for detailed table mapping examples**

---

## Development Workflow

### Daily Routine

```bash
# Morning: Pull latest changes
git pull

# Start dev server (runs in background)
npm run dev

# In another terminal, work on code...

# Test your changes
npm run check       # Type check
npm run lint        # Lint
npm run test:unit   # Unit tests

# Commit your work
git add .
git commit -m "Descriptive message"
git push
```

### Helpful Commands

```bash
# Reset database (careful: deletes all data!)
npx prisma migrate reset

# See pending migrations
npx prisma migrate status

# Format all code
npm run format

# Open Prisma Studio (database GUI)
npx prisma studio

# Install new package
npm install package-name

# Update dependencies
npm update
```

---

## Common Issues & Solutions

### Issue: "DATABASE_URL environment variable not found"

**Solution**: 
1. Ensure `.env` file exists in `IBL6/` directory
2. Verify `DATABASE_URL` is set correctly
3. Restart your terminal/IDE to reload environment

### Issue: "Can't connect to PostgreSQL"

**Solution**:
1. Check PostgreSQL is running: `brew services list` (macOS) or `sudo systemctl status postgresql` (Linux)
2. Verify port 5432 is not in use by another app
3. Check password is correct in `.env`
4. Try connecting with psql: `psql -U postgres -d ibl6_dev`

### Issue: "Prisma generate fails"

**Solution**:
1. Check syntax in `prisma/schema.prisma`
2. Ensure all model names are capitalized
3. Run `npx prisma validate` to see specific errors

### Issue: "npm install fails"

**Solution**:
1. Delete `node_modules/` and `package-lock.json`
2. Run `npm install` again
3. Check Node.js version: should be 18+

### Issue: "Port 5173 already in use"

**Solution**:
1. Kill existing dev server: `lsof -ti:5173 | xargs kill` (macOS/Linux)
2. Or use different port: `npm run dev -- --port 3000`

---

## Editor Setup (VS Code Recommended)

### Install Extensions

```
1. Svelte for VS Code (svelte.svelte-vscode)
2. Prisma (Prisma.prisma)
3. ESLint (dbaeumer.vscode-eslint)
4. Prettier (esbenp.prettier-vscode)
5. TypeScript and JavaScript Language Features (built-in)
```

### Configure Settings

Create `.vscode/settings.json`:

```json
{
  "editor.formatOnSave": true,
  "editor.defaultFormatter": "esbenp.prettier-vscode",
  "[svelte]": {
    "editor.defaultFormatter": "svelte.svelte-vscode"
  },
  "typescript.tsdk": "node_modules/typescript/lib",
  "files.associations": {
    "*.prisma": "prisma"
  }
}
```

---

## Learning Resources (While You Work)

### Quick References (Bookmark These)

- **Svelte Tutorial**: https://learn.svelte.dev/ (2 hours)
- **SvelteKit Docs**: https://kit.svelte.dev/docs (reference)
- **Prisma Docs**: https://www.prisma.io/docs (reference)
- **TypeScript Handbook**: https://www.typescriptlang.org/docs/ (reference)

### Recommended Learning Path

**Week 1** (While mapping schema):
- Read: Prisma Schema Reference (30 min)
- Read: Prisma Relations Guide (30 min)

**Week 2** (While building APIs):
- Complete: Svelte Tutorial (2 hours)
- Read: SvelteKit Routing (30 min)
- Read: SvelteKit API Routes (30 min)

**Week 3+** (While building frontend):
- Practice: Build components as you need them
- Reference: Look up specific topics as needed

---

## Next Steps After Setup

1. ✅ Verify all steps above completed
2. 📖 Read `MIGRATION_PLAN.md` Section "IMMEDIATE NEXT STEP" 
3. 🏗️ Begin mapping database tables (Day 1-3)
4. 💬 Ask questions in Discord/Slack if stuck
5. 📊 Track progress and celebrate small wins!

---

## Getting Help

### Documentation
- This repo: See `MIGRATION_PLAN.md` for detailed instructions
- Architecture: See `MIGRATION_ARCHITECTURE.md` for diagrams
- Summary: See `MIGRATION_EXECUTIVE_SUMMARY.md` for overview

### Community
- SvelteKit Discord: https://svelte.dev/chat
- Prisma Discord: https://pris.ly/discord
- Stack Overflow: Tag questions with `sveltekit`, `prisma`, `typescript`

### AI Assistants
- GitHub Copilot: Helps write code
- ChatGPT: Helps explain concepts
- Claude: Helps with architecture decisions

---

## Success Checklist

Before moving to Week 2, ensure:

- [ ] PostgreSQL installed and running
- [ ] IBL6 project dependencies installed
- [ ] Environment variables configured
- [ ] Prisma can connect to database
- [ ] Development server runs without errors
- [ ] All 60+ tables mapped in Prisma schema
- [ ] `npx prisma generate` completes successfully
- [ ] Sample data seeded in development database
- [ ] Prisma Studio shows all tables with data

**When all checked**: You're ready for Step 2 (Building APIs)! 🎉

---

*Get started now: `cd IBL6 && npm install`*  
*Questions? Check MIGRATION_PLAN.md for details*  
*Last Updated: 2025-10-30*
