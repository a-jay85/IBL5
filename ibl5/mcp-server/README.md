# IBL5 MySQL MCP Server

Model Context Protocol (MCP) server that provides Claude with read-only SQL access to the IBL5 basketball database for analyzing player statistics, ratings, and team compositions.

## Features

- **Read-Only Access**: Safe database queries with no modification capabilities
- **Player Analysis**: Query player statistics, ratings, and historical data
- **Team Analytics**: Analyze team compositions, standings, and performance
- **Game Data**: Access box scores, schedules, and game results
- **Draft & Contracts**: Review draft history, free agency, and contract information

## Project Structure

```
ibl5/mcp-server/
├── src/
│   ├── index.ts              # Main MCP server entry point
│   ├── db/                   # Database connection and pooling
│   ├── tools/                # MCP tool implementations
│   └── utils/                # Utility functions and validators
├── tests/                    # Jest test files
├── logs/                     # Query logs and audit trails
├── package.json              # Node.js dependencies
├── tsconfig.json             # TypeScript configuration
└── .env.example              # Environment variable template
```

## Setup

### 1. Install Dependencies

```bash
cd ibl5/mcp-server
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env
# Edit .env with your MySQL credentials
```

For MAMP users on macOS, use the socket path:
```env
DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
```

### 3. Build the Server

```bash
npm run build
```

### 4. Run Tests

```bash
npm test
```

## Development

```bash
# Watch mode for development
npm run dev

# Lint code
npm run lint
npm run lint:fix

# Run tests with coverage
npm test -- --coverage
```

## Database Schema

The server connects to the IBL5 MySQL database with access to:

- **Player Tables**: `ibl_plr`, `ibl_hist`, `ibl_plr_chunk`
- **Team Tables**: `ibl_team_info`, `ibl_team_history`, `ibl_standings`
- **Game Tables**: `ibl_schedule`, `ibl_box_scores`, `ibl_box_scores_teams`
- **Contract Tables**: `ibl_fa_offers`, `ibl_trade_*`
- **Draft Tables**: `ibl_draft`, `ibl_draft_picks`

See [DATABASE_GUIDE.md](../../DATABASE_GUIDE.md) for complete schema reference.

## Security

- **Read-Only**: All queries use SELECT statements only
- **SQL Injection Protection**: Prepared statements with mysql2
- **Input Validation**: Zod schemas validate all inputs
- **Query Logging**: All queries logged for audit trails

## MCP Integration

This server implements the Model Context Protocol, allowing Claude Desktop or other MCP clients to:

1. Query player statistics and ratings
2. Analyze team compositions
3. Review historical performance data
4. Compare players and teams
5. Track draft and free agency activity

## License

MIT

## Related Documentation

- [Development Guide](../../DEVELOPMENT_GUIDE.md)
- [Database Guide](../../DATABASE_GUIDE.md)
- [API Guide](../../API_GUIDE.md)
