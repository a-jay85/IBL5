<script lang="ts">
    export let headers: string[] = [];
    export let sortColumn: string = '';
    export let sortDirection: 'asc' | 'desc' = 'asc';

    // ✅ Map header display names to actual property names
    const columnMap: Record<string, string> = {
        'Pos': 'pos',
        'Name': 'name', 
        'min': 'min',
        'fgm': 'fgm',
        'fga': 'fga', 
        'ftm': 'ftm',
        'fta': 'fta',
        '3pm': '3pm',
        '3pa': '3pa',
        'pts': 'pts',
        'orb': 'orb',
        'reb': 'reb',
        'ast': 'ast',
        'stl': 'stl',
        'blk': 'blk',
        'tov': 'tov',
        'pf': 'pf'
    };
        // ✅ Get sort icon for column header
    function getSortIcon(header: string): string {
        const column = columnMap[header] || header;
        if (sortColumn !== column) return '↕️'; // Unsorted
        return sortDirection === 'asc' ? '↑' : '↓';
    }

    // ✅ Check if column is currently being sorted
    function isActiveSortColumn(header: string): boolean {
        const column = columnMap[header] || header;
        return sortColumn === column;
    }

    // ✅ Handle column header clicks for sorting
    function handleSort(header: string) {
        const column = columnMap[header] || header;
        
        if (sortColumn === column) {
            // Same column - toggle direction
            sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            // New column - set default direction based on column type
            sortColumn = column;
            // Most stats should default to descending (highest first)
            // Name and position should default to ascending (alphabetical)
            sortDirection = ['name', 'pos'].includes(column) ? 'asc' : 'desc';
        }
        
        console.log(`Sorting by ${column} ${sortDirection}`);
    }
</script>

<tr>
    {#each headers as header, index}
        <th 
            class="cursor-pointer select-none hover:bg-base-200 transition-colors px-2 py-3 min-w-10 text-center
                    {isActiveSortColumn(header) ? 'bg-primary/20 text-primary font-bold' : ''}
                    {header === 'Name' ? 'sticky opacity-100 left-0 z-30 bg-base-100 border-r border-base-300 shadow-lg min-w-32' : ''}"
            onclick={() => handleSort(header)}
            title="Click to sort by {header}"
        >
            <div class="flex items-center gap-1 justify-center">
                <span class="font-semibold">
                    {header === 'Pos' || header === 'Name' ? header : header.toUpperCase()}
                </span>
                <span class="text-xs opacity-60">
                    {getSortIcon(header)}
                </span>
            </div>
        </th>
    {/each}
</tr>