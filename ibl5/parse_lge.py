#!/usr/bin/env python3
"""
IBL5.lge File Parser
====================
Parses the Jump Shot Basketball .lge (league) file format.

File Structure:
- Total size: 64,000 bytes (640 rows x 100 bytes per row)
- All data is ASCII text, space-padded (0x20)
- The file is NOT purely row-oriented; some sections use fixed-width records
  that span across 100-byte row boundaries

Sections:
1. Header (offset 0x0000, 160 bytes)
   - League format description (32 bytes)
   - Playoff format: 4 x 8-byte fields (32 bytes)
   - Conference names: 2 x 16-byte fields (32 bytes)
   - Division names: 4 x 16-byte fields (64 bytes)

2. Team Entries (offset 0x00A0, 28 x 72 bytes = 2016 bytes)
   Each team record is 72 bytes:
   - Team name: 32 bytes (space-padded)
   - Control type: 8 bytes ("Computer" or spaces for human-controlled)
   - Conference: 16 bytes (space-padded)
   - Division: 16 bytes (space-padded)

3. Season Info (rows 39-40, offsets 0x0F3C-0x0FA3)
   - Year: 4 chars (e.g. "2006")
   - Season number: 1 char
   - Game/week numbers: additional fields

4. Boolean Flag Arrays (rows 100-106, offsets 0x2710-0x296E)
   - 32-character strings of '0' and '1' (one per team slot)
   - Various league configuration flags

5. Numeric Configuration (rows 106 and 120)
   - League parameters and statistics/seed values

6. Trailing Padding (rows ~121-639)
   - All spaces (0x20) to fill out to 64,000 bytes
"""

import sys
from pathlib import Path


def read_field(data: bytes, offset: int, length: int) -> str:
    """Read a fixed-width ASCII field, stripping trailing spaces."""
    return data[offset:offset + length].decode('ascii').rstrip()


def parse_header(data: bytes) -> dict:
    """Parse the 160-byte header section."""
    header = {}

    # Row 0, first 32 bytes: league format description
    header['format_description'] = read_field(data, 0, 32)

    # Playoff format: 4 x 8-byte fields at offset 32
    header['playoff_formats'] = []
    for i in range(4):
        pf = read_field(data, 32 + i * 8, 8)
        if pf:
            header['playoff_formats'].append(pf)

    # Conference names: 2 x 16-byte fields at offset 64
    header['conferences'] = []
    for i in range(2):
        conf = read_field(data, 64 + i * 16, 16)
        if conf:
            header['conferences'].append(conf)

    # Division names: 4 x 16-byte fields at offset 96
    header['divisions'] = []
    for i in range(4):
        div = read_field(data, 96 + i * 16, 16)
        if div:
            header['divisions'].append(div)

    return header


def parse_teams(data: bytes) -> list:
    """Parse 28 team entries starting at offset 0x00A0 (160)."""
    teams = []
    team_start = 160  # 0x00A0
    team_size = 72

    for i in range(32):
        offset = team_start + i * team_size
        if offset + team_size > len(data):
            break

        name = read_field(data, offset, 32)
        control = read_field(data, offset + 32, 8)
        conference = read_field(data, offset + 40, 16)
        division = read_field(data, offset + 56, 16)

        if not name and not conference:
            continue

        teams.append({
            'index': i + 1,
            'name': name,
            'control': control if control else 'Human',
            'conference': conference,
            'division': division,
            'offset': f'0x{offset:04X}',
        })

    return [t for t in teams if t['name']]


def parse_season_info(data: bytes) -> dict:
    """Parse season/year info from rows 39-40 (offsets 0x0F3C-0x0FA3)."""
    season = {}
    season['year'] = read_field(data, 0x0F98, 4)
    season['season_number'] = read_field(data, 0x0F9C, 4)
    season['field_1'] = read_field(data, 0x0FA0, 2)
    season['field_2'] = read_field(data, 0x0FA2, 4)
    season['raw'] = read_field(data, 0x0F98, 16)
    return season


def parse_flag_arrays(data: bytes) -> list:
    """Parse boolean flag arrays from rows 100-105."""
    ROW = 100
    flags = []

    flag_rows = {
        100: 'Flag Array 1 (all 1s)',
        101: 'Flag Array 2 (all 1s)',
        102: 'Flag Array 3 (all 1s)',
        103: 'Flag Array 4 (all 0s)',
    }

    for row_num, description in flag_rows.items():
        offset = row_num * ROW
        raw = read_field(data, offset, 32)
        values = [int(c) for c in raw if c in '01']
        flags.append({
            'row': row_num,
            'offset': f'0x{offset:04X}',
            'description': description,
            'raw': raw,
            'values': values,
            'ones_count': sum(values),
            'zeros_count': len(values) - sum(values),
        })

    # Row 104: TWO 33-char flag strings
    row104 = read_field(data, 104 * ROW, ROW)
    parts_104 = row104.split()
    for idx, part in enumerate(parts_104):
        if all(c in '01' for c in part) and len(part) > 1:
            values = [int(c) for c in part]
            flags.append({
                'row': 104,
                'offset': f'0x{104 * ROW:04X}',
                'description': f'Flag Array 5.{idx + 1} (32 zeros + trailing 1)',
                'raw': part,
                'values': values,
                'ones_count': sum(values),
                'zeros_count': len(values) - sum(values),
            })

    # Row 105: "00   111111111111111111111111111111110"
    row105 = read_field(data, 105 * ROW, ROW)
    parts_105 = row105.split()
    for idx, part in enumerate(parts_105):
        if all(c in '01' for c in part):
            values = [int(c) for c in part]
            flags.append({
                'row': 105,
                'offset': f'0x{105 * ROW:04X}',
                'description': f'Flag Array 6.{idx + 1} '
                               f'({"mixed" if 0 in values and 1 in values else "uniform"})',
                'raw': part,
                'values': values,
                'ones_count': sum(values),
                'zeros_count': len(values) - sum(values),
            })

    return flags


def parse_numeric_config(data: bytes) -> list:
    """Parse numeric configuration values from rows 106 and 120."""
    ROW = 100
    configs = []

    # Row 106: "1 0 0 116350  100"
    row106_raw = read_field(data, 106 * ROW, ROW)
    row106_parts = row106_raw.split()
    configs.append({
        'row': 106,
        'offset': f'0x{106 * ROW:04X}',
        'raw': row106_raw,
        'values': row106_parts,
        'description': 'Config block 1',
    })

    # Row 120: "1\x000 0 10619   152697  102427  173554  3"
    row120_offset = 120 * ROW
    row120_raw_bytes = data[row120_offset:row120_offset + ROW]
    row120_display = row120_raw_bytes.replace(b'\x00', b' ').decode('ascii').rstrip()
    row120_parts = row120_display.split()
    configs.append({
        'row': 120,
        'offset': f'0x{row120_offset:04X}',
        'raw': row120_display,
        'raw_bytes_hex': row120_raw_bytes[:40].hex(' '),
        'values': row120_parts,
        'description': 'Config block 2 (contains null byte at position 1)',
    })

    return configs


def hex_dump(data: bytes, start: int, length: int, label: str = '') -> None:
    """Print a hex dump of a region."""
    if label:
        print(f'\n--- {label} ---')
    end = min(start + length, len(data))
    for offset in range(start, end, 16):
        hex_part = ' '.join(f'{data[i]:02x}' for i in range(offset, min(offset + 16, end)))
        ascii_part = ''.join(
            chr(data[i]) if 32 <= data[i] < 127 else '.'
            for i in range(offset, min(offset + 16, end))
        )
        print(f'  {offset:04x}: {hex_part:<48s}  {ascii_part}')


def main() -> None:
    filepath = Path(__file__).parent / 'IBL5.lge'
    if len(sys.argv) > 1:
        filepath = Path(sys.argv[1])

    if not filepath.exists():
        print(f'Error: File not found: {filepath}')
        sys.exit(1)

    data = filepath.read_bytes()
    print(f'File: {filepath}')
    print(f'Size: {len(data):,} bytes ({len(data)} bytes)')
    print(f'Structure: {len(data) // 100} rows x 100 bytes/row')

    # =========================================================================
    # SECTION 1: Header
    # =========================================================================
    print('\n' + '=' * 70)
    print('SECTION 1: HEADER (offset 0x0000, 160 bytes)')
    print('=' * 70)

    header = parse_header(data)
    print(f'  Format:      {header["format_description"]}')
    print(f'  Playoffs:    {", ".join(header["playoff_formats"])}')
    print(f'  Conferences: {", ".join(header["conferences"])}')
    print(f'  Divisions:   {", ".join(header["divisions"])}')

    hex_dump(data, 0, 160, 'Header hex dump')

    # =========================================================================
    # SECTION 2: Team Entries
    # =========================================================================
    print('\n' + '=' * 70)
    print('SECTION 2: TEAM ENTRIES (offset 0x00A0, 72 bytes each)')
    print('=' * 70)

    teams = parse_teams(data)
    print(f'  Total teams found: {len(teams)}')
    print()
    print(f'  {"#":>3s}  {"Offset":<8s}  {"Team Name":<16s}  {"Control":<10s}  {"Conference":<10s}  {"Division":<10s}')
    print(f'  {"---":>3s}  {"--------":<8s}  {"----------------":<16s}  {"----------":<10s}  {"----------":<10s}  {"----------":<10s}')

    for t in teams:
        print(f'  {t["index"]:3d}  {t["offset"]:<8s}  {t["name"]:<16s}  {t["control"]:<10s}  '
              f'{t["conference"]:<10s}  {t["division"]:<10s}')

    # Conference/Division summary
    print()
    by_conf = {}
    for t in teams:
        key = (t['conference'], t['division'])
        by_conf.setdefault(key, []).append(t['name'])

    for (conf, div), team_names in sorted(by_conf.items()):
        print(f'  {conf} / {div}: {", ".join(team_names)}')

    human_teams = [t for t in teams if t['control'] == 'Human']
    computer_teams = [t for t in teams if t['control'] == 'Computer']
    print(f'\n  Computer-controlled: {len(computer_teams)} teams')
    print(f'  Human-controlled:    {len(human_teams)} teams'
          f' ({", ".join(t["name"] for t in human_teams)})')

    # =========================================================================
    # SECTION 3: Season Info
    # =========================================================================
    print('\n' + '=' * 70)
    print('SECTION 3: SEASON INFO (rows 39-40, offset 0x0F98)')
    print('=' * 70)

    season = parse_season_info(data)
    print(f'  Raw data:      "{season["raw"]}"')
    print(f'  Year:          {season["year"]}')
    print(f'  Season #:      {season["season_number"]}')
    print(f'  Field 1:       {season["field_1"]}')
    print(f'  Field 2:       {season["field_2"]}')

    hex_dump(data, 0x0F98, 16, 'Season info hex dump')

    # =========================================================================
    # SECTION 4: Boolean Flag Arrays
    # =========================================================================
    print('\n' + '=' * 70)
    print('SECTION 4: BOOLEAN FLAG ARRAYS (rows 100-105, offset 0x2710)')
    print('=' * 70)

    flags = parse_flag_arrays(data)
    for f in flags:
        print(f'\n  Row {f["row"]} [{f["offset"]}]: {f["description"]}')
        print(f'    Raw:   "{f["raw"]}"')
        print(f'    Ones:  {f["ones_count"]}, Zeros: {f["zeros_count"]}, '
              f'Length: {len(f["values"])}')

    hex_dump(data, 0x2710, 640,
             'Boolean flag region hex dump (rows 100-106)')

    # =========================================================================
    # SECTION 5: Numeric Configuration
    # =========================================================================
    print('\n' + '=' * 70)
    print('SECTION 5: NUMERIC CONFIGURATION (rows 106, 120)')
    print('=' * 70)

    configs = parse_numeric_config(data)
    for c in configs:
        print(f'\n  Row {c["row"]} [{c["offset"]}]: {c["description"]}')
        print(f'    Raw:    "{c["raw"]}"')
        print(f'    Values: {c["values"]}')
        if 'raw_bytes_hex' in c:
            print(f'    Hex:    {c["raw_bytes_hex"]}')

    print('\n  --- Interpretation Attempt ---')

    if len(configs) >= 2:
        c1 = configs[0]['values']
        c2 = configs[1]['values']
        print(f'\n  Config Block 1 (row 106): {c1}')
        if len(c1) >= 5:
            print(f'    [0] = {c1[0]:>8s}  (possible boolean/flag)')
            print(f'    [1] = {c1[1]:>8s}  (possible boolean/flag)')
            print(f'    [2] = {c1[2]:>8s}  (possible boolean/flag)')
            print(f'    [3] = {c1[3]:>8s}  (possible large numeric: '
                  f'attendance? salary cap? random seed?)')
            print(f'    [4] = {c1[4]:>8s}  (possible percentage or count)')

        print(f'\n  Config Block 2 (row 120): {c2}')
        if len(c2) >= 8:
            print(f'    [0] = {c2[0]:>8s}  (null-separated from next; '
                  f'byte 0x2EE1 is 0x00)')
            print(f'    [1] = {c2[1]:>8s}  (possible boolean/flag)')
            print(f'    [2] = {c2[2]:>8s}  (possible boolean/flag)')
            print(f'    [3] = {c2[3]:>8s}  (possible seed/counter)')
            print(f'    [4] = {c2[4]:>8s}  (possible seed/counter)')
            print(f'    [5] = {c2[5]:>8s}  (possible seed/counter)')
            print(f'    [6] = {c2[6]:>8s}  (possible seed/counter)')
            print(f'    [7] = {c2[7]:>8s}  (possible small config value)')

    hex_dump(data, 0x2960, 128,
             'Numeric config row 106 hex dump')
    hex_dump(data, 0x2EE0, 64,
             'Numeric config row 120 hex dump (note null byte at offset 0x2EE1)')

    # =========================================================================
    # SECTION 6: Complete Non-Empty Row Listing
    # =========================================================================
    print('\n' + '=' * 70)
    print('ALL NON-EMPTY ROWS (100 bytes each)')
    print('=' * 70)

    ROW = 100
    for r in range(640):
        row_data = data[r * ROW:(r + 1) * ROW]
        display = row_data.replace(b'\x00', b'.').decode('ascii', errors='replace').rstrip()
        if display.strip():
            print(f'  Row {r:3d} [0x{r * ROW:04X}]: "{display.strip()}"')

    # =========================================================================
    # SUMMARY
    # =========================================================================
    print('\n' + '=' * 70)
    print('FILE STRUCTURE SUMMARY')
    print('=' * 70)
    print(f"""
  File size:          64,000 bytes (640 rows x 100 bytes/row)
  Encoding:           ASCII, space-padded (0x20)
  Actual data:        ~12,039 bytes (18.8% of file)
  Trailing padding:   ~51,961 bytes of spaces

  Layout:
  +-------------------------------------------------------------------+
  | Offset 0x0000 (160 bytes) - HEADER                                |
  |   League format, playoff rules, conference/division names         |
  +-------------------------------------------------------------------+
  | Offset 0x00A0 (2016 bytes) - 28 TEAM ENTRIES (72 bytes each)     |
  |   name(32) + control(8) + conference(16) + division(16)          |
  |   Max capacity: 32 teams (4 empty slots remain)                  |
  +-------------------------------------------------------------------+
  | Offset 0x0880-0x0F97 - EMPTY (gap of 1816 bytes)                 |
  +-------------------------------------------------------------------+
  | Offset 0x0F98 (12 bytes) - SEASON INFO (rows 39-40)              |
  |   Year, season number, additional fields                         |
  +-------------------------------------------------------------------+
  | Offset 0x0FA4-0x270F - EMPTY (gap of 5996 bytes)                 |
  +-------------------------------------------------------------------+
  | Offset 0x2710 (rows 100-105) - BOOLEAN FLAG ARRAYS               |
  |   32-char strings of 0s and 1s (one bit per team slot)           |
  +-------------------------------------------------------------------+
  | Offset 0x2968 (row 106) - NUMERIC CONFIG BLOCK 1                 |
  |   Flags + large numeric values                                   |
  +-------------------------------------------------------------------+
  | Offset 0x297A-0x2EDF - EMPTY (gap of 1382 bytes)                 |
  +-------------------------------------------------------------------+
  | Offset 0x2EE0 (row 120) - NUMERIC CONFIG BLOCK 2                 |
  |   Contains a null byte (0x00) at position 1; seeds/counters      |
  +-------------------------------------------------------------------+
  | Offset 0x2F07-0xF9FF - TRAILING PADDING (all spaces)             |
  +-------------------------------------------------------------------+

  Notes:
  - Team records are NOT aligned to 100-byte row boundaries; they pack
    contiguously as 72-byte records starting at byte 160.
  - The boolean flag arrays ARE row-aligned (one per 100-byte row).
  - The null byte at offset 0x2EE1 (row 120, byte 1) is the only
    non-ASCII, non-space byte in the entire file.
  - 4 teams (Pistons, Kings, Bullets, Mavericks) have empty control
    fields, meaning they are human-controlled.
  - 4 team slots (indices 29-32) are empty, suggesting the league
    supports up to 32 teams but currently has 28.
""")


if __name__ == '__main__':
    main()
