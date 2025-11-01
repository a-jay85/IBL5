# Svelte + PostgreSQL Migration Plan for IBL5

## Executive Summary

This document outlines a comprehensive strategy to migrate the IBL5 codebase from its current PHP-Nuke/MySQL architecture to a modern Svelte/SvelteKit frontend with PostgreSQL database backend. The migration leverages the existing IBL6 prototype as a foundation and provides a phased approach that minimizes disruption while maximizing modernization benefits.

## Current State Analysis

### Technology Stack (IBL5 - Legacy)
- **Framework**: PHP-Nuke (legacy CMS framework from ~2007)
- **Backend**: PHP 7.x+ with procedural and object-oriented mixed patterns
- **Database**: MySQL 5.5.5-10.6.20-MariaDB with mixed MyISAM/InnoDB engines
- **Frontend**: Server-rendered PHP with inline HTML/JavaScript
- **Size**: ~1,413 PHP files, 127MB codebase
- **Architecture**: Monolithic module-based structure

### Key Business Features
- Fantasy basketball league management
- Player roster management and depth charts
- Trade system and free agency
- Statistics tracking and game simulation integration
- Waiver wire system
- All-Star and End-of-Year voting
- Team management and salary cap tracking
- 55+ functional modules in `ibl5/modules/`

### Existing Modernization (IBL6 - Prototype)
- **Framework**: SvelteKit 2.x with Svelte 5.0
- **Backend**: TypeScript with Vite build system
- **Database**: MySQL (with Prisma ORM ready for PostgreSQL)
- **Size**: 1.1MB codebase (nascent implementation)
- **Features Implemented**: Basic team/player models, partial UI components
- **Infrastructure**: Tailwind CSS, DaisyUI, Playwright tests, Vitest

### Database Characteristics
- **Tables**: 200+ tables with `ibl_*` and `nuke_*` prefixes
- **Character Sets**: Mixed latin1 and utf8mb4
- **Storage Engines**: Mixed MyISAM (legacy) and InnoDB (modern)
- **Key Entities**: 
  - Players (`ibl_plr`): ~500-1000 active players
  - Teams (`ibl_team_info`): 30 teams
  - Historical stats across multiple seasons
  - Complex contract and salary cap data

## Target State Vision

### Technology Stack (IBL6 - Production)
- **Frontend**: Svelte 5.0 + SvelteKit 2.x
- **Backend**: SvelteKit API routes (TypeScript)
- **Database**: PostgreSQL 15+ with Prisma ORM
- **Build**: Vite for fast development and optimized production builds
- **Styling**: Tailwind CSS + DaisyUI component library
- **Testing**: Vitest (unit) + Playwright (e2e)
- **Deployment**: Modern hosting (Vercel/Netlify or self-hosted with Docker)

### Architecture Principles
- **API-First**: RESTful API design with clear separation of concerns
- **Component-Based**: Reusable Svelte components for UI consistency
- **Type Safety**: Full TypeScript coverage for reliability
- **Modern Standards**: SSR/SSG with SvelteKit for performance and SEO
- **Progressive Enhancement**: Works without JavaScript where possible
- **Responsive Design**: Mobile-first approach with Tailwind utilities

## Migration Strategy Overview

The migration follows a **strangler fig pattern** - gradually replacing old system components while maintaining operational continuity. We'll build the new system alongside the old one, routing features to the new implementation as they're completed.

### Three-Phase Approach

**Phase 1: Foundation & Core Features (Months 1-3)**
- Database migration and setup
- Core data models and API
- Authentication and authorization
- Critical path features (roster, stats display)

**Phase 2: Feature Parity (Months 4-8)**
- Transaction systems (trades, waivers, free agency)
- Administrative tools
- Historical data and reporting
- User management features

**Phase 3: Enhancement & Cutover (Months 9-12)**
- Advanced features and UX improvements
- Performance optimization
- Data validation and cleanup
- Production cutover and legacy system retirement

---

## IMMEDIATE NEXT STEP (Week 1)

### Step 1: Database Migration - Schema Analysis & PostgreSQL Conversion

**Objective**: Create a PostgreSQL-compatible schema and migration scripts from the existing MySQL database.

#### Actions

1. **Analyze Current MySQL Schema** (Day 1-2)
   ```bash
   # Already have: ibl5/schema.sql (MariaDB export)
   # Review for PostgreSQL incompatibilities:
   - MEDIUMINT → INTEGER
   - TINYINT → SMALLINT or BOOLEAN
   - AUTO_INCREMENT → SERIAL or GENERATED ALWAYS
   - DATE '0000-00-00' → NULL (PostgreSQL doesn't support zero dates)
   - ENUM types → CHECK constraints or separate lookup tables
   - MyISAM storage engine references → remove (PostgreSQL doesn't use storage engines)
   ```

2. **Create PostgreSQL Schema** (Day 3-4)
   ```bash
   # Create new file: IBL6/prisma/schema-postgres.prisma
   # Convert existing MySQL schema.prisma to PostgreSQL
   # Key changes:
   - Change datasource provider from "mysql" to "postgresql"
   - Add proper foreign key constraints (missing in current MyISAM tables)
   - Update data types for PostgreSQL compatibility
   - Add indexes for common query patterns
   - Define proper relationships between models
   ```

3. **Create Migration Scripts** (Day 5)
   ```bash
   # Create: IBL6/migrations/mysql-to-postgres/
   # Scripts to:
   - Export data from MySQL (using mysqldump with custom format)
   - Transform data format (handle date/character encoding issues)
   - Import to PostgreSQL (using COPY commands)
   - Validate data integrity post-migration
   ```

4. **Set Up Development PostgreSQL Instance** (Day 5)
   ```bash
   # Options:
   - Docker Compose for local development
   - Cloud provider (ElephantSQL, Supabase, AWS RDS)
   
   # Create: IBL6/docker-compose.yml
   services:
     postgres:
       image: postgres:15-alpine
       environment:
         POSTGRES_DB: ibl6_dev
         POSTGRES_USER: ibl_user
         POSTGRES_PASSWORD: dev_password
       ports:
         - "5432:5432"
       volumes:
         - postgres_data:/var/lib/postgresql/data
   ```

#### Deliverables
- [ ] PostgreSQL-compatible Prisma schema (`schema-postgres.prisma`)
- [ ] Data migration scripts with validation
- [ ] Docker Compose configuration for local PostgreSQL
- [ ] Migration documentation with rollback procedures
- [ ] Data comparison report (MySQL vs PostgreSQL test migration)

#### Success Criteria
- All 200+ tables successfully converted to PostgreSQL format
- Test data migration completes without errors
- Data integrity verification passes (row counts, sample queries match)
- Foreign key relationships properly established
- Development team can run PostgreSQL locally with Docker

---

## SUBSEQUENT STEPS (Progressive Zoom-Out)

### Step 2: API Layer Foundation (Week 2-3)

**Objective**: Build the core API infrastructure using SvelteKit server routes.

#### Actions

1. **Define API Architecture** (Day 1)
   - RESTful endpoint structure: `/api/v1/{resource}`
   - Authentication middleware using JWT or session-based auth
   - Error handling patterns and response formats
   - Rate limiting and security headers

2. **Implement Core Data Access Layer** (Day 2-5)
   - Prisma client setup and connection pooling
   - Repository pattern for database access
   - Base CRUD operations for key entities (Teams, Players)
   - Query optimization and caching strategy

3. **Build Essential API Endpoints** (Day 6-10)
   ```typescript
   // Priority endpoints:
   GET  /api/v1/teams              // List all teams
   GET  /api/v1/teams/:id          // Team details
   GET  /api/v1/players            // List players (with filters)
   GET  /api/v1/players/:id        // Player details
   GET  /api/v1/players/:id/stats  // Player statistics
   GET  /api/v1/standings          // League standings
   ```

4. **Authentication System** (Day 11-15)
   - Migrate user authentication from PHP-Nuke
   - Implement JWT or session-based auth in SvelteKit
   - Password hashing with bcrypt
   - User session management
   - Role-based access control (owner, admin, commissioner)

#### Deliverables
- [ ] API design documentation (OpenAPI/Swagger spec)
- [ ] Prisma client configuration with connection pooling
- [ ] Core API endpoints with TypeScript types
- [ ] Authentication middleware and session management
- [ ] API testing suite (Vitest unit tests)
- [ ] Postman/Thunder Client collection for API testing

#### Success Criteria
- All core endpoints return correct data from PostgreSQL
- Authentication flow works end-to-end
- API tests achieve 80%+ coverage
- Response times under 200ms for typical queries
- Proper error handling and validation

---

### Step 3: Frontend Core Components (Week 4-6)

**Objective**: Build reusable Svelte components and core user interface pages.

#### Actions

1. **Component Library Setup** (Day 1-3)
   - Design system documentation (colors, typography, spacing)
   - Base components (Button, Input, Card, Modal, Table)
   - Layout components (Header, Footer, Navigation, Sidebar)
   - Form components with validation
   - Loading states and error boundaries

2. **Team & Player Pages** (Day 4-8)
   ```
   Routes to implement:
   /teams              → Teams list with standings
   /teams/[id]         → Team detail page (roster, stats)
   /players            → Players list (sortable, filterable)
   /players/[id]       → Player detail page (stats, contract)
   ```

3. **Data Display Components** (Day 9-12)
   - Statistics tables with sorting/filtering
   - Player cards with key stats
   - Team rosters with depth chart visualization
   - Standings table with dynamic calculations
   - Game schedule display

4. **State Management** (Day 13-15)
   - Svelte stores for global state (auth, teams, players)
   - Client-side caching strategy
   - Optimistic UI updates
   - Real-time data sync considerations

#### Deliverables
- [ ] Component library with Storybook documentation
- [ ] Core page routes implemented
- [ ] Responsive design for mobile/tablet/desktop
- [ ] Component tests (vitest-browser-svelte)
- [ ] Accessibility audit (WCAG 2.1 AA compliance)
- [ ] Performance benchmarks (Lighthouse scores 90+)

#### Success Criteria
- All components are responsive and accessible
- Pages load in under 2 seconds
- No console errors or warnings
- Visual design matches approved mockups
- Component tests achieve 70%+ coverage

---

### Step 4: Critical Features - Roster Management (Week 7-9)

**Objective**: Implement the most critical user-facing features for day-to-day league operations.

#### Actions

1. **Depth Chart System** (Day 1-5)
   - Convert `Depth_Chart_Entry` module to Svelte
   - Drag-and-drop depth chart editor
   - Position eligibility validation
   - Auto-save functionality
   - Historical depth chart tracking

2. **Player Statistics Display** (Day 6-10)
   - Season statistics pages
   - Career totals and averages
   - Historical comparisons
   - Advanced metrics calculations
   - Export functionality (CSV, PDF)

3. **Team Management Dashboard** (Day 11-15)
   - Roster overview with salary cap display
   - Contract status and expirations
   - Depth chart quick edit
   - Team history and achievements
   - Owner contact and preferences

#### Deliverables
- [ ] Depth chart editor with drag-and-drop
- [ ] Statistics pages with filtering and sorting
- [ ] Team dashboard with real-time cap calculations
- [ ] Mobile-optimized roster views
- [ ] E2E tests for critical user flows (Playwright)

#### Success Criteria
- Depth charts can be edited and saved successfully
- Statistics display accurately matches PHP version
- Salary cap calculations are 100% accurate
- All features work on mobile devices
- No data loss or corruption

---

### Step 5: Transaction Systems (Week 10-14)

**Objective**: Build the trade, waiver, and free agency systems.

#### Actions

1. **Waiver Wire System** (Day 1-7)
   - Convert `Waivers` module to Svelte
   - Player drop to waivers workflow
   - Player claim from waivers workflow
   - 24-hour wait period enforcement
   - Salary cap validation
   - Email/Discord notifications

2. **Trade System** (Day 8-14)
   - Trade proposal creation UI
   - Multi-team trade support
   - Salary cap impact calculator
   - Trade review and approval workflow
   - Trade history and reporting

3. **Free Agency** (Day 15-21)
   - Free agent bidding system
   - Offer submission and tracking
   - Bid comparison tools
   - Contract negotiation workflow
   - Signing announcements

#### Deliverables
- [ ] Waiver wire interface with claim/drop
- [ ] Trade proposal system with validation
- [ ] Free agency bidding interface
- [ ] Transaction history pages
- [ ] Notification system (email/Discord integration)
- [ ] Admin approval workflows

#### Success Criteria
- All transaction types can be completed end-to-end
- Validation prevents invalid transactions
- Notifications sent successfully
- Transaction history accurately tracked
- Admin tools allow oversight and management

---

### Step 6: Administrative Tools (Week 15-18)

**Objective**: Build commissioner and admin interfaces for league management.

#### Actions

1. **League Configuration** (Day 1-5)
   - Season management (create new season, roll over)
   - Salary cap configuration
   - Trade deadline and key dates
   - Rule modifications and settings
   - Team assignments

2. **Draft System** (Day 6-10)
   - Draft board interface
   - Real-time draft tracking
   - Pick trading
   - Draft history
   - Prospect management

3. **Voting Systems** (Day 11-15)
   - All-Star voting (convert `ASG_Results`)
   - End-of-Year awards voting (convert `EOY_Results`)
   - Vote tallying and results display
   - Historical voting records

4. **Administrative Reports** (Day 16-21)
   - Transaction reports
   - Salary cap reports
   - Activity logs
   - User management
   - Data export tools

#### Deliverables
- [ ] Commissioner dashboard
- [ ] Draft interface with real-time updates
- [ ] Voting systems (All-Star, EOY awards)
- [ ] Reporting suite
- [ ] User management tools
- [ ] Audit logs

#### Success Criteria
- Commissioners can perform all league management tasks
- Draft system supports live drafts
- Voting systems accurately tally results
- Reports provide actionable insights
- Admin tools are intuitive and efficient

---

### Step 7: Historical Data & Reporting (Week 19-22)

**Objective**: Migrate and display historical league data.

#### Actions

1. **Historical Statistics** (Day 1-7)
   - Career statistics displays
   - Season-by-season breakdowns
   - League records and milestones
   - Team history pages
   - Player career trajectories

2. **Archives** (Day 8-14)
   - Historical game results
   - Past transactions
   - Draft history
   - Award winners by year
   - Championship banners

3. **Analytics & Insights** (Day 15-21)
   - Advanced metrics calculations
   - Trend analysis
   - Player comparisons
   - Team performance analytics
   - Predictive modeling

#### Deliverables
- [ ] Historical statistics pages
- [ ] Archive section with full history
- [ ] Analytics dashboard
- [ ] Player comparison tools
- [ ] Data visualization components (charts, graphs)

#### Success Criteria
- All historical data accurately migrated
- Statistics match legacy system
- Archives are searchable and browsable
- Analytics provide meaningful insights
- Visualizations are clear and informative

---

### Step 8: Performance Optimization (Week 23-25)

**Objective**: Optimize application performance and user experience.

#### Actions

1. **Database Optimization** (Day 1-5)
   - Query performance analysis
   - Index optimization
   - Connection pooling tuning
   - Caching strategy implementation
   - Data denormalization where appropriate

2. **Frontend Performance** (Day 6-10)
   - Code splitting and lazy loading
   - Image optimization
   - Bundle size reduction
   - Service worker for offline capability
   - CDN configuration

3. **API Optimization** (Day 11-15)
   - Response caching (Redis)
   - API rate limiting
   - Compression (gzip/brotli)
   - GraphQL evaluation (if beneficial)
   - WebSocket for real-time features

#### Deliverables
- [ ] Performance audit report
- [ ] Optimized database indexes
- [ ] Caching layer (Redis/Memcached)
- [ ] Optimized frontend bundles
- [ ] CDN configuration
- [ ] Performance monitoring setup (e.g., DataDog, New Relic)

#### Success Criteria
- Page load times under 1 second
- API response times under 100ms (p95)
- Lighthouse performance score 95+
- Database query times under 50ms (p95)
- Support for 1000+ concurrent users

---

### Step 9: Testing & Quality Assurance (Week 26-28)

**Objective**: Comprehensive testing and quality assurance before production cutover.

#### Actions

1. **Automated Testing** (Day 1-7)
   - Unit test coverage to 80%+
   - Integration tests for API endpoints
   - E2E tests for critical user flows
   - Visual regression testing
   - Performance testing

2. **Manual Testing** (Day 8-14)
   - User acceptance testing (UAT)
   - Cross-browser testing
   - Mobile device testing
   - Accessibility testing
   - Security testing

3. **Data Validation** (Day 15-21)
   - Compare legacy vs new system data
   - Verify calculations (salary cap, stats)
   - Check data integrity
   - Test edge cases
   - Validate historical data

#### Deliverables
- [ ] Comprehensive test suite
- [ ] UAT sign-off documentation
- [ ] Bug tracking and resolution
- [ ] Security audit report
- [ ] Accessibility compliance report
- [ ] Performance test results

#### Success Criteria
- Test coverage exceeds 80%
- Zero critical bugs
- UAT approved by league owners
- Security vulnerabilities addressed
- Accessibility standards met

---

### Step 10: Deployment & Cutover (Week 29-30)

**Objective**: Deploy the new system to production and retire the legacy system.

#### Actions

1. **Pre-Deployment** (Day 1-3)
   - Production environment setup
   - Database migration rehearsal
   - Backup and rollback procedures
   - Monitoring and alerting setup
   - Communication plan

2. **Deployment** (Day 4-5)
   - Deploy application to production
   - Migrate production database
   - Configure DNS and SSL
   - Enable monitoring
   - Verify all systems operational

3. **Post-Deployment** (Day 6-10)
   - Monitor for issues
   - Address any critical bugs
   - User support and training
   - Gather feedback
   - Performance tuning

4. **Legacy System Retirement** (Day 11-15)
   - Parallel run period (both systems active)
   - Gradual traffic migration
   - Archive legacy system
   - Backup legacy data
   - Decommission old infrastructure

#### Deliverables
- [ ] Production deployment checklist
- [ ] Monitoring dashboards
- [ ] Backup and disaster recovery procedures
- [ ] User documentation and training materials
- [ ] Post-deployment support plan
- [ ] Legacy system archive

#### Success Criteria
- Zero downtime during cutover
- All users can access new system
- No critical issues in first week
- User satisfaction scores high
- Legacy system successfully retired

---

## Risk Assessment & Mitigation

### High-Risk Areas

1. **Data Migration Complexity**
   - **Risk**: Data corruption or loss during MySQL to PostgreSQL migration
   - **Mitigation**: 
     - Extensive testing on staging data
     - Automated data validation scripts
     - Rollback procedures documented and tested
     - Parallel run period to verify data accuracy

2. **Feature Parity**
   - **Risk**: Missing features cause user frustration
   - **Mitigation**: 
     - Feature inventory and prioritization
     - Regular user feedback during development
     - Beta testing with subset of users
     - Phased rollout of new features

3. **Performance at Scale**
   - **Risk**: New system slower than legacy system
   - **Mitigation**: 
     - Performance testing throughout development
     - Database query optimization
     - Caching strategy
     - Scalability testing with realistic data volumes

4. **User Adoption**
   - **Risk**: Users resist change to new interface
   - **Mitigation**: 
     - User involvement in design process
     - Comprehensive training and documentation
     - Similar UI/UX to legacy system where appropriate
     - Support resources readily available

5. **Timeline Overruns**
   - **Risk**: Project takes longer than estimated
   - **Mitigation**: 
     - Aggressive buffer time built into estimates
     - Regular progress reviews and adjustments
     - Prioritization of core features
     - Ability to scale team if needed

### Medium-Risk Areas

- Integration with existing tools (Discord, email)
- Browser compatibility issues
- Mobile responsiveness challenges
- Third-party dependency changes
- Security vulnerabilities

---

## Success Metrics

### Technical Metrics
- **Performance**: Page load times under 1 second, API response times under 100ms
- **Reliability**: 99.9% uptime, zero data loss incidents
- **Code Quality**: 80%+ test coverage, zero critical security vulnerabilities
- **Maintainability**: Clear documentation, modular architecture, TypeScript coverage

### User Metrics
- **Adoption**: 95%+ of active users migrated within first month
- **Satisfaction**: User satisfaction score 8/10 or higher
- **Engagement**: Equal or higher user engagement vs legacy system
- **Support**: Fewer support tickets than legacy system

### Business Metrics
- **Cost**: Hosting costs reduced by 30%+ (modern infrastructure efficiency)
- **Development Velocity**: 50%+ faster feature development post-migration
- **Bugs**: 70% reduction in bug reports
- **Scalability**: Support 5x current user load without performance degradation

---

## Resource Requirements

### Team Composition (Recommended)
- **Lead Developer** (1 FTE): Architecture, database migration, core API
- **Frontend Developer** (1 FTE): Svelte components, UI/UX implementation
- **QA Engineer** (0.5 FTE): Testing strategy, automated tests, UAT coordination
- **DevOps Engineer** (0.3 FTE): Infrastructure, deployment, monitoring
- **Product Owner** (0.2 FTE): Requirements, prioritization, user liaison

### Tools & Infrastructure
- **Development**: Docker, VS Code, Git/GitHub
- **Database**: PostgreSQL 15+ (cloud or self-hosted)
- **Hosting**: Vercel/Netlify (or Docker on VPS/cloud)
- **Monitoring**: DataDog, Sentry, or similar
- **Communication**: Discord/Slack for team coordination

### Budget Considerations
- **Infrastructure**: $50-200/month (database, hosting, CDN)
- **Tools**: $50-100/month (monitoring, error tracking)
- **Development Time**: ~6-8 months at stated team capacity

---

## Alternative Approaches Considered

### Approach 1: Big Bang Migration
**Description**: Migrate entire system at once
**Pros**: Faster, cleaner cut
**Cons**: High risk, long development time before any value delivered
**Decision**: Rejected - too risky for live system

### Approach 2: Hybrid PHP/Svelte System
**Description**: Keep PHP backend, add Svelte frontend
**Pros**: Faster initial migration, less backend work
**Cons**: Technical debt remains, two systems to maintain
**Decision**: Rejected - doesn't achieve modernization goals

### Approach 3: Incremental Module Migration (Selected)
**Description**: Strangler fig pattern - migrate module by module
**Pros**: Lower risk, continuous value delivery, parallel operation
**Cons**: Longer total timeline, some duplication during transition
**Decision**: Selected - best balance of risk and progress

---

## Conclusion

This migration plan provides a comprehensive roadmap from the current PHP-Nuke/MySQL architecture to a modern Svelte/PostgreSQL stack. By following the strangler fig pattern and building incrementally, we minimize risk while maximizing the benefits of modern web technologies.

The key to success is:
1. **Start with a solid foundation** (database and API)
2. **Focus on core features first** (roster, stats, transactions)
3. **Test continuously** (automated and manual testing)
4. **Communicate regularly** (with users and stakeholders)
5. **Be prepared to adapt** (adjust plan based on learnings)

With disciplined execution and the right team, this migration will position IBL5 for long-term success with a maintainable, scalable, and modern codebase.

---

## Appendix: Technology Decision Rationale

### Why Svelte over React/Vue?
- **Performance**: Compile-time optimization, smaller bundle sizes
- **Simplicity**: Less boilerplate, more readable code
- **Learning Curve**: Easier for new developers to learn
- **Existing Investment**: IBL6 prototype already started with Svelte

### Why PostgreSQL over MySQL?
- **Data Integrity**: Better constraint enforcement and ACID compliance
- **Advanced Features**: Better JSON support, window functions, CTEs
- **Ecosystem**: Rich extension ecosystem (PostGIS, full-text search)
- **Standards Compliance**: More SQL standard compliant
- **Future-Proof**: Industry trend toward PostgreSQL for new projects

### Why SvelteKit over Other Frameworks?
- **SSR/SSG**: Built-in server-side rendering for SEO and performance
- **File-Based Routing**: Intuitive routing structure
- **API Routes**: Backend API in same codebase
- **Modern Tooling**: Vite-powered development experience
- **TypeScript**: First-class TypeScript support

### Why Prisma over Other ORMs?
- **Type Safety**: Auto-generated TypeScript types
- **Developer Experience**: Intuitive query API
- **Migrations**: Robust migration system
- **Multi-Database**: Easy to switch between databases
- **Active Development**: Well-maintained and actively developed
