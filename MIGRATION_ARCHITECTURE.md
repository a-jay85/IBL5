# IBL5 Migration Architecture

## Current Architecture (IBL5)

```
┌─────────────────────────────────────────────────────────┐
│                    User's Browser                       │
│  (HTML rendered on server, minimal JavaScript)          │
└────────────────────┬────────────────────────────────────┘
                     │ HTTP Requests
                     ▼
┌─────────────────────────────────────────────────────────┐
│               Apache/PHP Web Server                      │
│                                                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │            PHP-Nuke Framework                       │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │   55 Modules (procedural PHP)                │  │ │
│  │  │   - Player stats                              │  │ │
│  │  │   - Team management                           │  │ │
│  │  │   - Waivers (refactored with classes)        │  │ │
│  │  │   - Depth charts (refactored)                │  │ │
│  │  │   - Trading, Free Agency, etc.               │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  │                                                      │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │   Classes (modern PHP)                        │  │ │
│  │  │   - Waivers\*                                 │  │ │
│  │  │   - DepthChart\*                             │  │ │
│  │  │   - Voting\*                                  │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────┬────────────────────────────────────┘
                     │ MySQL Queries (mysqli)
                     ▼
┌─────────────────────────────────────────────────────────┐
│              MySQL/MariaDB 10.6.20                       │
│  ┌────────────────────────────────────────────────────┐ │
│  │  Mixed Storage Engines:                             │ │
│  │  - MyISAM (legacy tables)                          │ │
│  │  - InnoDB (newer tables)                           │ │
│  │                                                      │ │
│  │  Mixed Character Sets:                              │ │
│  │  - latin1 (legacy)                                 │ │
│  │  - utf8mb4 (modern)                                │ │
│  │                                                      │ │
│  │  60+ Tables:                                        │ │
│  │  - ibl_* (league data)                             │ │
│  │  - nuke_* (PHP-Nuke system)                        │ │
│  │  - Laravel migration tables                        │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## Target Architecture (IBL6)

```
┌─────────────────────────────────────────────────────────┐
│              Modern Web Browser                          │
│  ┌────────────────────────────────────────────────────┐ │
│  │  SvelteKit SPA (runs in browser)                   │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │  Svelte Components (TypeScript)               │  │ │
│  │  │  - PlayerCard                                 │  │ │
│  │  │  - TeamCard                                   │  │ │
│  │  │  - DepthChartForm                            │  │ │
│  │  │  - WaiverWireInterface                       │  │ │
│  │  │  - TradingInterface                          │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  │                                                      │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │  Svelte Stores (State Management)             │  │ │
│  │  │  - auth                                       │  │ │
│  │  │  - iblPlayers                                │  │ │
│  │  │  - teamsStore                                │  │ │
│  │  │  - leaderStore                               │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────┬────────────────────────────────────┘
                     │ REST API Calls (JSON)
                     ▼
┌─────────────────────────────────────────────────────────┐
│          SvelteKit Server (Node.js)                      │
│  ┌────────────────────────────────────────────────────┐ │
│  │         SvelteKit API Routes                        │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │  /api/players                                 │  │ │
│  │  │  /api/teams                                   │  │ │
│  │  │  /api/games                                   │  │ │
│  │  │  /api/stats                                   │  │ │
│  │  │  /api/waivers                                │  │ │
│  │  │  /api/trades                                  │  │ │
│  │  │  /api/depth-chart                            │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  │                                                      │ │
│  │  ┌──────────────────────────────────────────────┐  │ │
│  │  │  Business Logic (TypeScript)                  │  │ │
│  │  │  - Validation                                 │  │ │
│  │  │  - Authorization                              │  │ │
│  │  │  - Business Rules                             │  │ │
│  │  └──────────────────────────────────────────────┘  │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────┬────────────────────────────────────┘
                     │ Prisma ORM (Type-safe queries)
                     ▼
┌─────────────────────────────────────────────────────────┐
│              Prisma Client Layer                         │
│  ┌────────────────────────────────────────────────────┐ │
│  │  Auto-generated TypeScript Types                    │ │
│  │  Type-safe query builder                           │ │
│  │  Connection pooling                                │ │
│  │  Migration management                              │ │
│  └────────────────────────────────────────────────────┘ │
└────────────────────┬────────────────────────────────────┘
                     │ SQL Queries
                     ▼
┌─────────────────────────────────────────────────────────┐
│              PostgreSQL 15+                              │
│  ┌────────────────────────────────────────────────────┐ │
│  │  Unified Architecture:                              │ │
│  │  - InnoDB equivalent (default)                     │ │
│  │  - UTF-8 encoding (utf8mb4 equivalent)            │ │
│  │  - Foreign key constraints                         │ │
│  │  - ACID compliance                                 │ │
│  │                                                      │ │
│  │  60+ Tables (migrated):                             │ │
│  │  - ibl_* (league data)                             │ │
│  │  - User management tables                          │ │
│  │  - Session tables                                  │ │
│  └────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────┘
```

## Transition Architecture (Running Both)

```
                    ┌──────────────────────────┐
                    │    Users' Browsers        │
                    └───────────┬───────────────┘
                                │
                    ┌───────────┴────────────┐
                    │                        │
            Feature Flag or                  │
            Subdomain Routing                │
                    │                        │
        ┌───────────▼────────┐   ┌──────────▼────────────┐
        │   IBL5 (Legacy)    │   │   IBL6 (New)          │
        │   iblhoops.net     │   │   beta.iblhoops.net   │
        └───────────┬────────┘   └──────────┬────────────┘
                    │                        │
            ┌───────┴────────┐      ┌────────┴───────┐
            │                │      │                │
            ▼                │      ▼                │
    ┌──────────────┐         │  ┌──────────────┐    │
    │ MySQL/MariaDB│◄────────┼──│ PostgreSQL   │    │
    └──────────────┘         │  └──────────────┘    │
            ▲                │                       │
            │                │                       │
            └────────────────┴───────────────────────┘
                    Data Sync (during migration)
```

## Data Flow Comparison

### Current (IBL5) - Server-Side Rendering

```
User clicks link
    ↓
Browser sends HTTP request
    ↓
PHP receives request
    ↓
PHP queries MySQL
    ↓
MySQL returns data
    ↓
PHP generates HTML
    ↓
Server sends complete HTML page
    ↓
Browser displays page
    ↓
(Repeat for every navigation)
```

### Target (IBL6) - Client-Side SPA

```
User visits site (first time)
    ↓
Browser loads SvelteKit app
    ↓
App hydrates (becomes interactive)
    ↓
User clicks link
    ↓
Svelte component updates (instant, no page reload)
    ↓
If data needed:
    ↓
    JavaScript fetches from /api/players
    ↓
    SvelteKit API route queries PostgreSQL via Prisma
    ↓
    PostgreSQL returns data
    ↓
    API returns JSON
    ↓
    JavaScript updates component
    ↓
    User sees new data (fast, no flicker)
```

## Component Tree Example

```
+page.svelte (Players List)
│
├── PlayerFilters.svelte
│   ├── TeamSelector.svelte
│   └── PositionSelector.svelte
│
├── PlayerGrid.svelte
│   └── PlayerCard.svelte (repeated for each player)
│       ├── PlayerAvatar.svelte
│       ├── PlayerStats.svelte
│       └── PlayerActions.svelte
│
└── Pagination.svelte
```

## File Structure Comparison

### IBL5 Structure
```
ibl5/
├── modules/
│   ├── Waivers/
│   │   └── index.php (25 lines, delegates to classes)
│   ├── Depth_Chart_Entry/
│   │   └── index.php (94 lines, delegates to classes)
│   └── [50+ other modules]
│
├── classes/
│   ├── Waivers/
│   │   ├── WaiversController.php
│   │   ├── WaiversValidator.php
│   │   ├── WaiversRepository.php
│   │   └── ...
│   └── DepthChart/
│       └── ...
│
└── mainfile.php (class autoloader)
```

### IBL6 Structure
```
IBL6/
├── src/
│   ├── routes/                    # Pages & API
│   │   ├── +page.svelte          # Home page
│   │   ├── players/
│   │   │   ├── +page.svelte      # Players list
│   │   │   └── [id]/
│   │   │       └── +page.svelte   # Player detail
│   │   └── api/                   # REST API
│   │       ├── players/
│   │       │   ├── +server.ts     # GET /api/players
│   │       │   └── [id]/
│   │       │       └── +server.ts  # GET /api/players/:id
│   │       └── teams/
│   │           └── +server.ts      # GET /api/teams
│   │
│   └── lib/
│       ├── components/            # Reusable UI
│       │   ├── PlayerCard.svelte
│       │   ├── TeamCard.svelte
│       │   └── StatsTable.svelte
│       ├── stores/                # State management
│       │   ├── auth.ts
│       │   └── players.ts
│       ├── models/                # TypeScript types
│       │   ├── Player.ts
│       │   └── Team.ts
│       ├── database/
│       │   └── prisma.ts          # Prisma client
│       └── utils/
│           └── validation.ts
│
└── prisma/
    └── schema.prisma              # Database schema
```

## Migration Phases Visualization

```
Phase 0: Foundation (NOW - Week 1)
├── ✅ SvelteKit scaffolded
├── ✅ Prisma configured
├── ✅ Basic components
├── 🔄 Complete schema mapping
└── 🔄 PostgreSQL setup

Phase 1: Database Setup (Week 2-3)
├── Install PostgreSQL
├── Complete Prisma schema (60+ models)
├── Create migration scripts
└── Seed development data

Phase 2: API Development (Week 4-7)
├── Player endpoints
├── Team endpoints
├── Schedule endpoints
└── Stats endpoints

Phase 3: Frontend Components (Week 8-13)
├── Read-only pages (weeks 8-9)
├── Interactive forms (weeks 10-11)
└── Complex features (weeks 12-13)

Phase 4: Authentication (Week 14-15)
├── JWT implementation
├── User migration
└── Permission system

Phase 5: Testing (Week 16-17)
├── Unit tests
├── E2E tests
└── Load testing

Phase 6: Deployment (Week 18)
├── Soft launch (beta.iblhoops.net)
├── User testing
└── Production cutover
```

## Technology Stack Comparison

| Layer | IBL5 (Current) | IBL6 (Target) |
|-------|----------------|---------------|
| **Frontend** | Server-rendered HTML | SvelteKit SPA |
| **Language** | PHP | TypeScript |
| **Styling** | Custom CSS | TailwindCSS + DaisyUI |
| **State** | Server session | Svelte stores |
| **Routing** | PHP-Nuke modules | SvelteKit file-based |
| **Backend** | PHP-Nuke framework | SvelteKit API routes |
| **Database** | MySQL/MariaDB | PostgreSQL |
| **ORM** | Raw mysqli queries | Prisma |
| **Testing** | PHPUnit | Vitest + Playwright |
| **Build** | None (interpreted) | Vite |
| **Deployment** | Apache + PHP | Node.js + Adapter |

## Performance Benefits

```
Metric               │ IBL5 (Current)  │ IBL6 (Target)   │ Improvement
─────────────────────┼─────────────────┼─────────────────┼─────────────
Initial Load         │ ~3-4 seconds    │ ~1 second       │ 3-4x faster
Subsequent Pages     │ ~2-3 seconds    │ ~100ms          │ 20-30x faster
                     │ (full reload)   │ (client nav)    │
API Response         │ N/A             │ ~100-200ms      │ New capability
Bundle Size          │ ~500KB HTML     │ ~150KB JS       │ 3x smaller
Time to Interactive  │ ~4-5 seconds    │ ~2 seconds      │ 2x faster
Server Resources     │ High (PHP)      │ Medium (Node)   │ More efficient
Database Queries     │ N queries/page  │ 1-2 per action  │ Optimized
Concurrent Users     │ ~100            │ ~1000+          │ 10x scale
```

---

*This document visualizes the architectural transformation from IBL5 to IBL6*  
*See MIGRATION_PLAN.md for implementation details*
