import Table from 'cli-table'

const input = `┌────┬───────┬─────────────┬─────────┬─────────┬──────────┬────────┬──────┬───────────┬──────────┬──────────┬──────────┬──────────┐
│ id │ name  │ namespace   │ version │ mode    │ pid      │ uptime │ ↺    │ status    │ cpu      │ mem      │ user     │ watching │
├────┼───────┼─────────────┼─────────┼─────────┼──────────┼────────┼──────┼───────────┼──────────┼──────────┼──────────┼──────────┤
│ 3  │ 10    │ default     │ N/A     │ fork    │ 24340    │ 17s    │ 0    │ online    │ 0%       │ 62.7mb   │ TITANS   │ disabled │
│ 1  │ 9     │ default     │ N/A     │ fork    │ 29588    │ 83m    │ 0    │ online    │ 0%       │ 42.2mb   │ TITANS   │ disabled │
└────┴───────┴─────────────┴─────────┴─────────┴──────────┴────────┴──────┴───────────┴──────────┴──────────┴──────────┴──────────┘
`;
const trimmedString = input.trim();
const lines = trimmedString.split('\n');
const headers = lines[1].split('│').map(header => header.trim()).filter(header => header.length > 0);
const tableData = [];

for (let i = 3; i < lines.length - 1; i++) {
  const cells = lines[i].split('│').map(cell => cell.trim()).filter(cell => cell.length > 0);
  const rowData = {};

  for (let j = 0; j < headers.length; j++) {
    rowData[headers[j]] = cells[j];
  }

  tableData.push(rowData);
}

const jsonData = JSON.stringify(tableData, null, 2);
console.log(jsonData);
