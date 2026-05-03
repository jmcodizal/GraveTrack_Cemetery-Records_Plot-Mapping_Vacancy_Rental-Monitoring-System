# GraveTrack Cemetery Map - PHP Conversion

## Overview
The cemetery map has been converted from a static HTML page with dummy data to a fully dynamic PHP application that integrates with the GraveTrack database. The system now supports 3 cemetery phases with proper data management.

## Cemetery Structure

### Phase 1: Traditional Cemetery (Blocks A-I)
- 9 blocks total
- Each block has 10 lots
- Traditional single-story burial layout

### Phase 2: Traditional Cemetery (Blocks T-Z)
- 7 blocks total (T, U, V, W, X, Y, Z)
- Each block has 10 lots
- Traditional single-story burial layout

### Phase 3: Apartment Court (Block AA)
- Single block with 4 sections
- Each section contains 50 individual lots
- Total: 200 lots (50 × 4 sections)
- Organized for apartment-style burial spaces

## Color Coding

- **Green**: Vacant lot (no burial records)
- **Red**: Occupied lot (has one or more burial records)

## New Files Created

### 1. Database Migration
**File**: `Database/db_migration_phases.sql`
- Adds `phase_id` column to `blocks` table
- Adds `lot_number` and `section_number` columns to `plots` table
- Adds `payment_status` column to `deceased` table
- Creates `phases` table with all 3 phases
- Creates `cemetery_map_view` for efficient data retrieval

### 2. Main Cemetery Map Page
**File**: `cemetery_map_v2.php`
- Displays all 3 cemetery phases with proper layout
- Dynamic lot rendering based on database data
- Search functionality (search by deceased name or plot code)
- Phase filtering
- Click any lot to view details
- Modal popup showing burial records
- Edit button links to edit page

**Features**:
- Responsive grid layout for lots
- Real-time color coding (green/red)
- Multiple deceased support per lot
- Payment status display
- Modal interface for viewing details

### 3. Edit Burial Record Page
**File**: `edit_burial_record.php`
- **Add New Records**: Create burial records for new plots
- **Edit Existing Records**: Modify deceased information, dates, payment status
- **Delete Records**: Remove burial records with confirmation
- **Bulk View**: See all records for a plot
- **Auto-create Plots**: Automatically creates plots if they don't exist

**Fields**:
- Deceased Name (required)
- Date of Birth
- Date of Death
- Gender
- Address
- Payment Status (Unpaid, Paid, Partial)

### 4. API Endpoint
**File**: `api/get_lot_details.php`
- Returns lot details as JSON
- Fetches all deceased records for a plot
- Calculates paid/unpaid counts
- Used by the modal popup via AJAX

## Database Tables Reference

### plots
```
- plot_id (PRIMARY KEY)
- plot_code (UNIQUE) - e.g., "A-1", "AA-S1-L1"
- block_id (FOREIGN KEY)
- lot_number
- section_number (for Phase 3 only)
- status
- created_at, updated_at
```

### deceased
```
- deceased_id (PRIMARY KEY)
- deceased_name
- plot_id (FOREIGN KEY)
- date_of_birth
- date_of_death
- gender
- address
- payment_status (unpaid, paid, partial)
- created_at
```

### phases
```
- phase_id (PRIMARY KEY)
- phase_number (1, 2, 3)
- phase_name
- description
```

### blocks
```
- block_id (PRIMARY KEY)
- phase_id (FOREIGN KEY)
- block_name (A, B, C, ..., AA)
- description
```

## Plot Code Naming Convention

### Phase 1 & 2 Blocks
- Format: `{BLOCK_LETTER}-{LOT_NUMBER}`
- Examples: `A-1`, `B-5`, `Z-10`, `AA-1`

### Phase 3 Apartment
- Format: `AA-S{SECTION}-L{LOT}`
- Example: `AA-S1-L1` (Block AA, Section 1, Lot 1)
- Example: `AA-S4-L50` (Block AA, Section 4, Lot 50)

## How to Use

### 1. Run Database Migration
Execute the migration script in phpMyAdmin:
```sql
-- Run all commands from Database/db_migration_phases.sql
```

### 2. Access Cemetery Map
```
http://localhost/GraveTrack/cemetery_map_v2.php
```

**Features**:
- View all 3 phases
- Search by deceased name or plot code
- Filter by phase
- Click any lot to view records
- Click "Edit Record" to manage data

### 3. Add New Burial Record
1. Click on an empty (green) lot
2. Click "Add Burial Record" button
3. Fill in the form with deceased information
4. Select payment status
5. Submit to create record

### 4. Edit Existing Record
1. Click on an occupied (red) lot
2. View all burial records in the modal
3. Click "Edit Record" button
4. Modify the information
5. Submit to update
6. Or delete by clicking the "Delete Record" button

### 5. Multiple Deceased Per Plot
- A single lot can have multiple burial records
- This is useful for family members buried in the same plot
- Each record has its own payment status
- All names appear in the lot preview

## Search and Filter

### Search
- Search by deceased name
- Search by plot code (e.g., "A-1", "AA-S1-L1")
- Results show across all phases

### Filter
- Filter by phase (Phase 1, 2, or 3)
- Combined with search for targeted results

## Payment Status Options

- **Unpaid**: No payment made (displayed in red badge)
- **Paid**: Fully paid (displayed in green badge)
- **Partial**: Partially paid (displayed in yellow badge)

## Notes

1. **Auto-Plot Creation**: When adding a burial record to a new plot, the system automatically creates the plot if it doesn't exist.

2. **Block Auto-Creation**: Blocks are also created automatically if they don't exist, but you may want to pre-populate them with proper phase assignments.

3. **Phase Assignment**: Make sure to run the migration and assign phase_id to existing blocks for proper filtering.

4. **Lot Rendering**:
   - Phase 1 & 2: Each block shows 10 lots
   - Phase 3: Each section shows 50 lots
   - This can be adjusted in the PHP code if needed

5. **Responsive Design**: The grid layout is responsive and works on mobile devices.

## Future Enhancements

- Bulk import from CSV
- Payment history tracking
- Advanced reporting
- Print/PDF export
- User access control
- Activity logging
- Photo upload for deceased
- Relationship tracking between deceased

## Troubleshooting

### Phase 3 Lots Not Showing
- Ensure the migration script was run
- Check that blocks are assigned to Phase 3
- Verify the database connection

### Plots Not Appearing
- Make sure the plot_code matches the expected format
- Check the block_id is correctly assigned
- Verify the phase_id is set for the block

### Payment Status Not Saving
- Ensure the deceased table has the payment_status column
- Rerun the migration if the column is missing

## Support
For issues or questions, refer to the database schema and the PHP code comments.
