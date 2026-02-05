# PridePath Download API

## Overview
This API provides public access to download the most recent PridePath spreadsheet files (State Laws and Local Laws). The API automatically serves the latest file based on the date in the filename.

## Endpoints

### Download Latest State Laws Spreadsheet
```
GET /api/pridepath-download.php?type=state
```
Downloads the most recent State Laws spreadsheet.

### Download Latest Local Laws Spreadsheet
```
GET /api/pridepath-download.php?type=local
```
Downloads the most recent Local Laws spreadsheet.

### Get Information About Both Files
```
GET /api/pridepath-download.php?type=both
```
Returns JSON with information about the most recent files:
```json
{
  "state": {
    "filename": "2025-01-27 State Laws.xlsx",
    "date": "2025-01-27",
    "download_url": "https://vcctest.org/api/pridepath-download.php?type=state",
    "size": 12345
  },
  "local": {
    "filename": "2025-01-27 Local Laws.xlsx",
    "date": "2025-01-27",
    "download_url": "https://vcctest.org/api/pridepath-download.php?type=local",
    "size": 23456
  }
}
```

## Response Codes
- `200 OK` - File found and download started
- `400 Bad Request` - Invalid type parameter
- `404 Not Found` - No file found

## Examples

### Using curl
```bash
# Download latest State Laws
curl -O -J "https://vcctest.org/api/pridepath-download.php?type=state"

# Download latest Local Laws
curl -O -J "https://vcctest.org/api/pridepath-download.php?type=local"

# Get JSON info about both
curl "https://vcctest.org/api/pridepath-download.php?type=both"
```

### Using wget
```bash
# Download latest State Laws
wget "https://vcctest.org/api/pridepath-download.php?type=state"
```

### In JavaScript
```javascript
// Download State Laws
window.location.href = 'https://vcctest.org/api/pridepath-download.php?type=state';

// Get info about both files
fetch('https://vcctest.org/api/pridepath-download.php?type=both')
  .then(response => response.json())
  .then(data => console.log(data));
```