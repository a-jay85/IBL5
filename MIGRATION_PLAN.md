# IBL5 to Modern Stack Migration Plan

## Executive Summary

This document outlines a pragmatic, speed-optimized migration strategy to move the IBL5 fantasy basketball platform from its current PHP-Nuke legacy foundation to a modern, maintainable stack while minimizing learning curve and development time.

**Current State:**
- **Backend**: PHP-Nuke framework (legacy) with partial Laravel coexistence
- **Frontend**: Server-rendered PHP templates with minimal JavaScript
- **Database**: MySQL/MariaDB 10.6.20 (mixed MyISAM/InnoDB, mixed latin1/utf8mb4)
- **Code Base**: ~19,000 lines across 55 modules, partially refactored with OOP patterns

**Target State:**
- **Frontend**: SvelteKit + TypeScript + Vite
- **Backend**: Keep PHP initially, transition to TypeScript/Node.js API layer
- **Database**: PostgreSQL with Prisma ORM
- **Architecture**: Modern component-based SPA with RESTful API

**Good News**: Migration to **SvelteKit + Prisma is already in progress!** The `IBL6/` directory contains a working foundation.

---

## Strategic Recommendations

### Framework Choice: SvelteKit ✅ (Already Started!)

**Why SvelteKit:**
- ✅ **Already chosen and scaffolded** in the `IBL6/` directory
- **Minimal Learning Curve**: Svelte has simpler syntax than React/Vue (HTML-like templates)
- **Fast Development**: Less boilerplate, reactive by default, no virtual DOM
- **SSR Built-in**: SvelteKit provides server-side rendering out of the box
- **TypeScript Native**: First-class TypeScript support
- **Small Bundle Size**: Compiles to vanilla JS, resulting in faster load times
- **Growing Ecosystem**: Mature enough with good community support

**Alternatives Considered:**
- **Next.js (React)**: More ecosystem but steeper learning curve, more verbose
- **Nuxt (Vue)**: Good but more complex API, heavier framework
- **Remix**: Newer, smaller ecosystem, more experimental

### Database Strategy: PostgreSQL with Prisma ORM

**Why PostgreSQL:**
- **Modern Features**: Better JSON support, array types, full ACID compliance
- **Scalability**: Superior concurrent connection handling
- **Data Integrity**: Robust foreign key constraints, better type system
- **Future-Proof**: Industry standard for modern applications
- **Migration Path**: Prisma abstracts database differences during transition

**Prisma ORM Benefits:**
- ✅ **Already configured** in `IBL6/prisma/schema.prisma`
- **Type Safety**: Auto-generated TypeScript types from schema
- **Migration System**: Version-controlled database changes
- **Query Builder**: Intuitive, type-safe query API
- **Multi-Database**: Easy to switch between MySQL and PostgreSQL during migration

---

## Migration Phases

### Phase 0: Foundation (CURRENT - Already In Progress!)

**Status**: ~30% complete based on `IBL6/` directory analysis

**What's Done:**
- ✅ SvelteKit project scaffolded with TypeScript
- ✅ Prisma ORM configured with MySQL
- ✅ Core models defined: Team, IblPlayer, BoxPlayer, BoxGame
- ✅ Basic components created: PlayerCard, LeaderCard, ExampleTable
- ✅ State management with Svelte stores
- ✅ Build pipeline with Vite
- ✅ Test setup with Vitest and Playwright

**Remaining Work:**
1. Complete Prisma schema migration (currently 4 models, need ~60 based on schema.sql)
2. Set up development database with sample data
3. Create API route structure in SvelteKit
4. Establish coding patterns and component library foundations

### Phase 1: Dual-Database Setup (2-3 weeks)

**Goal**: Run IBL6 alongside IBL5 with shared database access

**Tasks:**
1. **PostgreSQL Setup**
   - Install PostgreSQL locally and on production server
   - Create migration scripts from MySQL to PostgreSQL
   - Set up pg_dump/restore procedures
   - Configure connection pooling

2. **Database Sync Strategy**
   - Option A: Run both MySQL and PostgreSQL during transition (replicate data)
   - Option B: Direct cutover (faster, requires maintenance window)
   - **Recommendation**: Option B with good backup strategy

3. **Prisma Schema Completion**
   - Map all 60+ tables from `schema.sql` to Prisma models
   - Define relationships between models
   - Add indexes and constraints
   - Generate TypeScript types

4. **Data Migration**
   - Convert MyISAM tables to InnoDB (required for PostgreSQL migration)
   - Standardize character encoding (utf8mb4)
   - Clean up data inconsistencies
   - Create seeding scripts for development

### Phase 2: API Layer Development (3-4 weeks)

**Goal**: Create REST API endpoints in SvelteKit to replace PHP modules

**Strategy**: Prioritize by usage and dependencies

**High-Priority Endpoints (Core Functionality):**
1. **Player Management**
   - `GET /api/players` - List players with filtering
   - `GET /api/players/:id` - Player details
   - `GET /api/players/:id/stats` - Player statistics
   - Complexity: Medium (read-heavy, performance critical)

2. **Team Management**
   - `GET /api/teams` - List all teams
   - `GET /api/teams/:id` - Team details and roster
   - `GET /api/teams/:id/schedule` - Team schedule
   - Complexity: Low (mostly read operations)

3. **Schedule & Scores**
   - `GET /api/games` - Game schedule with filters
   - `GET /api/games/:id` - Box score details
   - Complexity: Medium (complex queries)

4. **Statistics Leaders**
   - `GET /api/stats/leaders` - League leaders by category
   - Complexity: Medium (aggregation queries)

**Medium-Priority Endpoints (User Interactions):**
5. **Depth Chart Management**
   - `GET /api/teams/:id/depth-chart`
   - `PUT /api/teams/:id/depth-chart`
   - Complexity: Medium (already refactored in PHP)

6. **Waiver Wire**
   - `GET /api/waivers`
   - `POST /api/waivers/claim`
   - `POST /api/waivers/drop`
   - Complexity: High (transactional logic)

7. **Free Agency**
   - `GET /api/free-agents`
   - `POST /api/offers`
   - Complexity: High (complex business rules)

8. **Trading**
   - `POST /api/trades/propose`
   - `GET /api/trades/:id`
   - `PUT /api/trades/:id/accept`
   - Complexity: High (validation-heavy)

**Low-Priority Endpoints (Admin & Utility):**
9. **Voting System**
   - `GET /api/votes/asg`
   - `POST /api/votes`
   - Complexity: Low

10. **News & Content**
    - `GET /api/news`
    - Already has some database tables
    - Complexity: Low

**Implementation Pattern:**
```typescript
// SvelteKit API Route Example
// routes/api/players/+server.ts
import { json } from '@sveltejs/kit';
import { prisma } from '$lib/database/prisma';

export async function GET({ url }) {
  const position = url.searchParams.get('position');
  const team = url.searchParams.get('team');
  
  const players = await prisma.iblPlayer.findMany({
    where: {
      ...(position && { pos: position }),
      ...(team && { teamname: team })
    },
    include: { team: true }
  });
  
  return json(players);
}
```

### Phase 3: Frontend Component Migration (4-6 weeks)

**Goal**: Build Svelte components to replace PHP modules

**Component Architecture:**
```
src/
├── routes/              # Pages (SvelteKit routing)
│   ├── +page.svelte    # Home page
│   ├── players/        # Player pages
│   ├── teams/          # Team pages
│   ├── schedule/       # Schedule pages
│   └── api/            # API routes
├── lib/
│   ├── components/     # Reusable UI components
│   │   ├── PlayerCard.svelte
│   │   ├── TeamCard.svelte
│   │   ├── DepthChart.svelte
│   │   └── StatsTable.svelte
│   ├── stores/         # State management
│   ├── models/         # TypeScript types
│   └── utils/          # Helper functions
```

**Migration Priority (by user traffic):**

**Week 1-2: Core Viewing Pages (Read-Only)**
1. Home page with league standings
2. Team roster pages
3. Player profile pages
4. Schedule/scores pages
5. Statistics leaders

**Week 3-4: Interactive Features (Forms)**
6. Depth chart entry
7. Search functionality
8. Voting interfaces

**Week 5-6: Advanced Features (Complex Transactions)**
9. Waiver wire interface
10. Free agency bidding
11. Trade proposal system
12. Admin interfaces

**Component Reuse Strategy:**
- Create a shared component library (`/lib/components/`)
- Use Svelte stores for shared state
- Implement consistent styling with TailwindCSS (already configured)
- Build reusable form components for CRUD operations

### Phase 4: Authentication & Authorization (1-2 weeks)

**Current System**: PHP-Nuke user management

**Migration Strategy:**
1. **Keep PHP Session Management Initially**
   - SvelteKit can validate PHP sessions via API
   - Minimal disruption to existing users

2. **Add JWT/Session Tokens**
   - Issue tokens after PHP auth validation
   - Store in httpOnly cookies
   - Validate in SvelteKit API routes

3. **User Migration**
   - Export users from `nuke_users` table
   - Import to new auth system (Firebase Auth or similar)
   - Maintain user IDs for backward compatibility

4. **Permission System**
   - Map existing PHP-Nuke roles to new system
   - Implement middleware for route protection
   - Add granular permissions for team operations

### Phase 5: Testing & Quality Assurance (2-3 weeks)

**Testing Strategy:**

1. **Unit Tests (Vitest)** - ✅ Already configured
   - Test business logic functions
   - Test Prisma queries with test database
   - Aim for 80%+ coverage on critical paths

2. **Component Tests**
   - Test Svelte components in isolation
   - Use Testing Library patterns
   - Mock API responses

3. **E2E Tests (Playwright)** - ✅ Already configured
   - Critical user flows (login, roster management, trades)
   - Browser compatibility testing
   - Performance benchmarks

4. **Integration Tests**
   - API endpoint tests
   - Database migration validation
   - Data consistency checks

5. **Load Testing**
   - Simulate game day traffic
   - Identify bottlenecks
   - Optimize database queries

### Phase 6: Deployment & Cutover (1 week)

**Pre-Deployment:**
1. Database migration and validation
2. DNS preparation
3. Backup strategy verification
4. Rollback plan documented

**Deployment Strategy:**
1. **Soft Launch** (Recommended)
   - Deploy IBL6 to subdomain (e.g., beta.iblhoops.net)
   - Invite power users for testing
   - Run in parallel with IBL5 for 1-2 weeks
   - Collect feedback and fix issues

2. **Feature Flags**
   - Gradually enable new features
   - Toggle between old/new for A/B testing
   - Quick rollback if issues arise

3. **Cutover Weekend**
   - Schedule maintenance window
   - Final database migration
   - Switch DNS/routing
   - Monitor closely for 24-48 hours

**Post-Deployment:**
1. Monitor error logs and performance
2. Address critical bugs immediately
3. Gather user feedback
4. Plan Phase 7 improvements

### Phase 7: Optimization & Enhancement (Ongoing)

**Performance Optimization:**
- Database query optimization
- API response caching
- Static asset optimization
- CDN setup for images/assets

**Feature Enhancements:**
- Real-time updates with WebSockets
- Mobile-responsive design improvements
- Progressive Web App (PWA) features
- Enhanced analytics dashboard

**Technical Debt Reduction:**
- Remove legacy PHP code
- Clean up unused database tables
- Consolidate CSS/styling
- Document architecture decisions

---

## Detailed Next Steps (Progressive Zoom)

### IMMEDIATE NEXT STEP (This Week)

**Task**: Complete the Prisma schema and set up PostgreSQL locally

**Why This First:**
- Foundation for all subsequent work
- Unblocks API development
- Allows parallel frontend/backend work

**Detailed Actions:**

#### Step 1.1: Install PostgreSQL (Day 1 - 2 hours)

```bash
# macOS
brew install postgresql@15
brew services start postgresql@15

# Ubuntu/Debian
sudo apt-get install postgresql-15 postgresql-contrib
sudo systemctl start postgresql

# Create database
createdb ibl6_dev
```

**Validation:**
```bash
psql -d ibl6_dev -c "SELECT version();"
# Should show PostgreSQL version
```

#### Step 1.2: Update Prisma Configuration (Day 1 - 1 hour)

```typescript
// IBL6/prisma/schema.prisma
datasource db {
  provider = "postgresql"  // Changed from "mysql"
  url      = env("DATABASE_URL")
}
```

**Environment Setup:**
```bash
# IBL6/.env
DATABASE_URL="postgresql://postgres:password@localhost:5432/ibl6_dev"
```

#### Step 1.3: Map Remaining Database Tables (Day 1-2 - 6-8 hours)

**Current**: 4 models defined (Team, IblPlayer, BoxPlayer, BoxGame)
**Target**: ~60 models from schema.sql

**Priority Order:**
1. Core player/team tables (ibl_plr, ibl_team_info) - ✅ Done
2. Statistics tables (ibl_*_stats, ibl_*_career_*) - 12 tables
3. Game/Schedule tables (ibl_schedule, ibl_box_scores_*) - 5 tables
4. Transaction tables (ibl_trade_*, ibl_fa_*, ibl_draft) - 8 tables
5. Voting tables (ibl_votes_*, ibl_awards) - 4 tables
6. Historical tables (ibl_hist, ibl_team_history) - 3 tables
7. PHP-Nuke tables (nuke_users, nuke_stories) - 10 tables
8. Miscellaneous (remaining tables) - 20 tables

**Model Template:**
```typescript
model IblHist {
  histid      Int      @id @default(autoincrement())
  year        Int
  name        String   @db.VarChar(32)
  tid         Int
  stats       String?  @db.Text
  
  player      IblPlayer @relation(fields: [name], references: [name])
  
  @@map("ibl_hist")
}
```

**Tools to Accelerate:**
```bash
# Use Prisma introspection to auto-generate models
npx prisma db pull --schema=./prisma/mysql-schema.prisma
# Then manually convert MySQL-specific types to PostgreSQL equivalents
```

#### Step 1.4: Create Migration Script (Day 3 - 4 hours)

**Purpose**: Convert MySQL data to PostgreSQL-compatible format

```typescript
// scripts/migrate-mysql-to-postgres.ts
import { PrismaClient as MySQLClient } from '@prisma/client-mysql';
import { PrismaClient as PostgresClient } from '@prisma/client-postgres';

const mysql = new MySQLClient();
const postgres = new PostgresClient();

async function migrateTeams() {
  const teams = await mysql.team.findMany();
  for (const team of teams) {
    await postgres.team.upsert({
      where: { teamid: team.teamid },
      update: team,
      create: team
    });
  }
  console.log(`Migrated ${teams.length} teams`);
}

// Continue for all tables...
```

**Run Migration:**
```bash
npm run db:migrate
# With progress bar and error handling
```

#### Step 1.5: Seed Development Database (Day 3 - 2 hours)

```typescript
// prisma/seed.ts
import { PrismaClient } from '@prisma/client';
const prisma = new PrismaClient();

async function main() {
  // Create sample teams
  await prisma.team.createMany({
    data: [
      { teamid: 1, city: "Los Angeles", name: "Lakers", ... },
      // ... more teams
    ]
  });
  
  // Create sample players
  await prisma.iblPlayer.createMany({
    data: [
      { pid: 1, name: "LeBron James", teamId: 1, ... },
      // ... more players
    ]
  });
}

main()
  .catch(console.error)
  .finally(() => prisma.$disconnect());
```

**Run Seeding:**
```bash
npx prisma db seed
```

#### Step 1.6: Validate Schema (Day 3 - 2 hours)

```bash
# Generate Prisma client
npx prisma generate

# Run test queries
npx prisma studio  # Opens GUI to browse data

# Test in code
npm run test:unit
```

**Success Criteria:**
- ✅ All 60+ tables mapped in Prisma schema
- ✅ PostgreSQL database created and seeded
- ✅ Prisma client generates without errors
- ✅ Sample queries return expected data
- ✅ Relationships work correctly (joins)

---

### NEXT STEP 2 (Week 2)

**Task**: Build First API Endpoints and Corresponding Frontend Pages

**Focus**: Player listing and details (most viewed pages)

#### Step 2.1: Create Player List API (Day 1)

```typescript
// IBL6/src/routes/api/players/+server.ts
import { json } from '@sveltejs/kit';
import { prisma } from '$lib/database/prisma';
import type { RequestHandler } from './$types';

export const GET: RequestHandler = async ({ url }) => {
  const team = url.searchParams.get('team');
  const position = url.searchParams.get('position');
  const limit = parseInt(url.searchParams.get('limit') || '50');
  
  const players = await prisma.iblPlayer.findMany({
    where: {
      ...(team && { teamname: team }),
      ...(position && { pos: position })
    },
    include: {
      team: true,
      boxPlayers: {
        take: 5,
        orderBy: { gameDate: 'desc' }
      }
    },
    take: limit,
    orderBy: { name: 'asc' }
  });
  
  return json({ 
    players,
    count: players.length 
  });
};
```

#### Step 2.2: Create Player List Page (Day 1-2)

```svelte
<!-- IBL6/src/routes/players/+page.svelte -->
<script lang="ts">
  import { onMount } from 'svelte';
  import PlayerCard from '$lib/components/PlayerCard.svelte';
  import type { IblPlayer } from '$lib/models/IblPlayer';
  
  let players: IblPlayer[] = [];
  let loading = true;
  let filters = { team: '', position: '' };
  
  async function loadPlayers() {
    loading = true;
    const params = new URLSearchParams();
    if (filters.team) params.set('team', filters.team);
    if (filters.position) params.set('position', filters.position);
    
    const response = await fetch(`/api/players?${params}`);
    const data = await response.json();
    players = data.players;
    loading = false;
  }
  
  onMount(loadPlayers);
</script>

<div class="container mx-auto p-4">
  <h1 class="text-3xl font-bold mb-4">IBL Players</h1>
  
  <div class="filters mb-4">
    <select bind:value={filters.team} on:change={loadPlayers}>
      <option value="">All Teams</option>
      <!-- Team options -->
    </select>
    
    <select bind:value={filters.position} on:change={loadPlayers}>
      <option value="">All Positions</option>
      <option value="PG">Point Guard</option>
      <!-- More positions -->
    </select>
  </div>
  
  {#if loading}
    <p>Loading players...</p>
  {:else}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      {#each players as player}
        <PlayerCard {player} />
      {/each}
    </div>
  {/if}
</div>
```

#### Step 2.3: Create Player Detail API (Day 2)

```typescript
// IBL6/src/routes/api/players/[id]/+server.ts
export const GET: RequestHandler = async ({ params }) => {
  const player = await prisma.iblPlayer.findUnique({
    where: { pid: parseInt(params.id) },
    include: {
      team: true,
      boxPlayers: {
        orderBy: { gameDate: 'desc' },
        take: 10
      },
      hist: {
        orderBy: { year: 'desc' }
      }
    }
  });
  
  if (!player) {
    throw error(404, 'Player not found');
  }
  
  return json({ player });
};
```

#### Step 2.4: Create Player Detail Page (Day 2-3)

```svelte
<!-- IBL6/src/routes/players/[id]/+page.svelte -->
<script lang="ts">
  import { page } from '$app/stores';
  import type { PageData } from './$types';
  
  export let data: PageData;
  const { player } = data;
</script>

<div class="container mx-auto p-4">
  <div class="player-header">
    <h1 class="text-4xl font-bold">{player.name}</h1>
    <p class="text-xl text-gray-600">{player.pos} - {player.team.name}</p>
  </div>
  
  <div class="stats-grid">
    <!-- Player stats display -->
  </div>
  
  <div class="recent-games">
    <h2 class="text-2xl font-bold">Recent Games</h2>
    <!-- Game log table -->
  </div>
</div>
```

**Success Criteria:**
- ✅ `/api/players` returns filtered player data
- ✅ `/players` page displays player cards
- ✅ `/players/[id]` shows detailed player information
- ✅ Filtering works (team, position)
- ✅ Performance <200ms for API calls
- ✅ Mobile responsive design

---

### NEXT STEP 3 (Week 3)

**Task**: Implement Team Pages and Schedule

**Why**: Second most viewed content, builds on player API

#### Step 3.1: Team List API (Day 1)
#### Step 3.2: Team Detail Page with Roster (Day 1-2)
#### Step 3.3: Schedule API with Game Details (Day 2)
#### Step 3.4: Schedule Page with Filters (Day 3)

---

### NEXT STEP 4 (Week 4-5)

**Task**: Depth Chart Management (First Interactive Feature)

**Why**: Already refactored in PHP, good test case for complex forms

#### Step 4.1: Read-only Depth Chart Display (Day 1)
#### Step 4.2: Editable Depth Chart Form (Day 2-3)
#### Step 4.3: Save API with Validation (Day 3-4)
#### Step 4.4: Email Notification (Day 4)
#### Step 4.5: Testing (Day 5)

---

### NEXT STEP 5 (Week 6-8)

**Task**: Waiver Wire System

**Why**: Time-sensitive transactions, good test of business logic

---

### NEXT STEP 6 (Week 9-10)

**Task**: Trading System

---

### NEXT STEP 7 (Week 11-12)

**Task**: Free Agency & Bidding

---

### NEXT STEP 8 (Week 13-14)

**Task**: Admin Features & Reports

---

### NEXT STEP 9 (Week 15-16)

**Task**: Authentication Migration

---

### NEXT STEP 10 (Week 17-18)

**Task**: Testing, Performance Tuning, Documentation

---

## Risk Mitigation

### Technical Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Data loss during migration | Medium | Critical | Multiple backups, test migrations, rollback plan |
| Performance degradation | Medium | High | Load testing, query optimization, caching strategy |
| User adoption resistance | High | Medium | Gradual rollout, training, maintain familiar UX |
| PostgreSQL incompatibilities | Low | Medium | Thorough testing, Prisma abstracts differences |
| SvelteKit learning curve | Low | Low | Good documentation, simple API, team training |

### Schedule Risks

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Underestimated complexity | High | High | Buffer time in estimates, prioritize ruthlessly |
| Key developer unavailable | Medium | High | Documentation, knowledge sharing, parallel work |
| Scope creep | High | Medium | Strict feature freeze, defer enhancements to Phase 7 |
| Third-party API changes | Low | Low | Version pinning, monitor changelogs |

---

## Resource Requirements

### Development Team (Minimum)

1. **Full-Stack Developer** (1 person, 18 weeks)
   - SvelteKit/TypeScript frontend development
   - API design and implementation
   - Database modeling and migration
   
2. **Part-Time DBA/DevOps** (0.25 FTE, 6 weeks)
   - PostgreSQL setup and tuning
   - Migration script development
   - Deployment automation

3. **QA/Testing** (0.5 FTE, 4 weeks)
   - Test plan creation
   - E2E test implementation
   - User acceptance testing

### Infrastructure

1. **Development Environment**
   - Local PostgreSQL database
   - Node.js 18+ runtime
   - Git repository access

2. **Staging Environment**
   - Cloud VM or container (2 CPU, 4GB RAM)
   - PostgreSQL instance
   - Subdomain for testing (beta.iblhoops.net)

3. **Production Environment**
   - Application server (4 CPU, 8GB RAM)
   - PostgreSQL server (4 CPU, 16GB RAM)
   - CDN for static assets
   - Backup storage (100GB+)

### Tools & Services

1. **Development**: VS Code, GitHub, npm
2. **Database**: PostgreSQL 15+, Prisma Studio
3. **Testing**: Vitest, Playwright, Postman
4. **Monitoring**: Error tracking (Sentry), Analytics
5. **Communication**: Slack/Discord for updates

---

## Success Metrics

### Performance Targets

- **Page Load Time**: <1 second (95th percentile)
- **API Response Time**: <200ms (average)
- **Time to Interactive**: <2 seconds
- **Lighthouse Score**: >90 (desktop), >80 (mobile)

### Reliability Targets

- **Uptime**: 99.5% (scheduled maintenance excluded)
- **Error Rate**: <0.1% of requests
- **Data Accuracy**: 100% (no data loss or corruption)

### User Experience Targets

- **Feature Parity**: 100% of IBL5 functionality
- **Mobile Usage**: >50% of traffic supported
- **User Satisfaction**: >4/5 average rating
- **Support Tickets**: <50% of pre-migration baseline

---

## Conclusion

This migration plan prioritizes **speed** (18-week timeline) and **ease of learning** (SvelteKit's gentle learning curve) while building on the excellent foundation already established in the `IBL6/` directory.

### Key Advantages of This Approach:

1. **Leverages Existing Work**: 30% head start with IBL6 folder
2. **Minimal Learning Curve**: Svelte is HTML-like, TypeScript is gradual
3. **Modern Stack**: Future-proof with industry-standard tools
4. **Incremental Migration**: Low-risk, feature-by-feature approach
5. **Type Safety**: TypeScript + Prisma eliminate entire classes of bugs
6. **Performance**: Svelte's compilation and PostgreSQL's efficiency
7. **Maintainability**: Clean separation of concerns, testable code

### Immediate Action Items:

**This Week:**
1. ✅ Review and approve this migration plan
2. 🔄 Complete Prisma schema mapping (Steps 1.1-1.6 above)
3. 🔄 Set up PostgreSQL locally and run first migration
4. 🔄 Validate data in development environment

**Next Week:**
5. Build first API endpoints (players)
6. Create first Svelte pages
7. Establish coding standards and patterns

The migration is well-positioned for success. With the foundation in place and this roadmap as a guide, the team can deliver a modern, maintainable platform in under 5 months while maintaining the site's functionality and user experience.

---

## Appendix A: Technology Stack Details

### Frontend Stack
- **Framework**: SvelteKit 2.x
- **Language**: TypeScript 5.x
- **Styling**: TailwindCSS 4.x + DaisyUI
- **State Management**: Svelte Stores
- **Build Tool**: Vite 7.x
- **Testing**: Vitest + Playwright
- **Package Manager**: npm

### Backend Stack (API Layer)
- **Runtime**: Node.js 18+ (LTS)
- **API Framework**: SvelteKit API routes
- **ORM**: Prisma 6.x
- **Database**: PostgreSQL 15+
- **Validation**: Zod or Yup
- **Authentication**: JWT + httpOnly cookies

### Database Stack
- **Primary DB**: PostgreSQL 15+
- **Connection Pooling**: PgBouncer or Prisma Pool
- **Backup**: pg_dump + automated snapshots
- **Migration Tool**: Prisma Migrate

### DevOps Stack
- **Version Control**: Git + GitHub
- **CI/CD**: GitHub Actions
- **Hosting**: TBD (Vercel, Netlify, or VPS)
- **Monitoring**: Error tracking + performance monitoring
- **Analytics**: Optional (Plausible, Umami, or similar)

---

## Appendix B: Learning Resources

### SvelteKit (Primary Framework)
- Official Tutorial: https://learn.svelte.dev/
- SvelteKit Docs: https://kit.svelte.dev/docs
- Video Course: "SvelteKit Full Course" (YouTube)
- Estimated Learning Time: 2-3 days for basics

### TypeScript
- TypeScript Handbook: https://www.typescriptlang.org/docs/handbook/
- Focus on: Types, Interfaces, Generics
- Estimated Learning Time: 1 week for working proficiency

### Prisma ORM
- Quickstart: https://www.prisma.io/docs/getting-started
- Schema Reference: https://www.prisma.io/docs/reference
- Estimated Learning Time: 2-3 days

### PostgreSQL
- PostgreSQL Tutorial: https://www.postgresqltutorial.com/
- Focus on: CRUD, Indexes, Relationships
- Estimated Learning Time: 1 week (if new to SQL)

### TailwindCSS
- Docs: https://tailwindcss.com/docs
- Play CDN: Quick experimentation
- Estimated Learning Time: 2-3 days

**Total Learning Investment**: 2-3 weeks for full stack proficiency (if starting from zero)

---

## Appendix C: Alternative Approaches Considered

### Alternative 1: Laravel + Vue (PHP-Native)
**Pros**: Keep PHP backend, incremental migration
**Cons**: Steeper learning curve (Vue 3 Composition API), heavier framework
**Decision**: Rejected - IBL6 already chose SvelteKit

### Alternative 2: Keep PHP-Nuke, Modern Frontend Only
**Pros**: Minimal backend changes
**Cons**: Technical debt remains, two-system maintenance burden
**Decision**: Rejected - Doesn't solve core architectural issues

### Alternative 3: Complete Rewrite (Big Bang)
**Pros**: Clean slate, optimal architecture
**Cons**: High risk, long timeline, user disruption
**Decision**: Rejected - Too risky, chosen incremental approach instead

### Alternative 4: Use Next.js Instead of SvelteKit
**Pros**: Larger ecosystem, more job market
**Cons**: More complex, steeper learning curve, heavier bundle
**Decision**: Rejected - SvelteKit already chosen and partially implemented

---

*Last Updated: 2025-10-30*
*Document Version: 1.0*
*Author: Copilot Coding Agent*
