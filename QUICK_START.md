# GraveTrack Cemetery Map - Quick Start Guide

## Step 1: Run Database Migration

1. Open phpMyAdmin
2. Select the `gravetrack_db` database
3. Go to the "SQL" tab
4. Copy and paste the contents of `Database/db_migration_phases.sql`
5. Click "Go" to execute

**OR** use the MySQL command line:
```bash
mysql -u root gravetrack_db < Database/db_migration_phases.sql
```

## Step 2: Verify Database Setup

Check that the following were created/updated:
- `phases` table with 3 rows (Phase 1, 2, 3)
- `blocks` table has `phase_id` column
- `plots` table has `lot_number` and `section_number` columns
- `deceased` table has `payment_status` column

## Step 3: Initial Setup (Optional but Recommended)

Run these SQL commands to set up initial blocks:

```sql
-- Phase 1 Blocks (A-I)
INSERT INTO phases (phase_number, phase_name) VALUES (1, 'Phase 1') ON DUPLICATE KEY UPDATE phase_number=1;
INSERT INTO blocks (block_name, phase_id) VALUES 
('A', 1), ('B', 1), ('C', 1), ('D', 1), ('E', 1), ('F', 1), ('G', 1), ('H', 1), ('I', 1);

-- Phase 2 Blocks (T-Z)
INSERT INTO phases (phase_number, phase_name) VALUES (2, 'Phase 2') ON DUPLICATE KEY UPDATE phase_number=2;
INSERT INTO blocks (block_name, phase_id) VALUES 
('T', 2), ('U', 2), ('V', 2), ('W', 2), ('X', 2), ('Y', 2), ('Z', 2);

-- Phase 3 Block (AA)
INSERT INTO phases (phase_number, phase_name) VALUES (3, 'Phase 3 - Apartment Court') ON DUPLICATE KEY UPDATE phase_number=3;
INSERT INTO blocks (block_name, phase_id) VALUES ('AA', 3);
```

## Step 4: Access the Cemetery Map

1. Open your browser
2. Navigate to: `http://localhost/GraveTrack/cemetery_map_v2.php`
3. You should see three phases with colored grid squares:
   - **Green squares** = Vacant lots
   - **Red squares** = Occupied lots

## Step 5: Add Your First Record

1. Click on any **green** lot
2. Click "Add Burial Record"
3. Fill in:
   - Plot code (auto-filled or custom)
   - Deceased name
   - Date of death (optional)
   - Payment status (choose: Unpaid, Paid, Partial)
4. Click "Add Record"

The lot will turn **red** and show the deceased name.

## Step 6: Search and Filter

- **Search**: Type name or plot code in the search box
- **Filter**: Select a phase from the dropdown
- **Clear**: Click "Clear Filters" to reset

## Common Tasks

### Edit a Burial Record
1. Click the occupied (red) lot
2. Click "Edit Record"
3. Modify the information
4. Click "Update Record"

### Delete a Burial Record
1. Click the occupied lot
2. Click "Edit Record"
3. Scroll to the record
4. Click "Delete Record" in the record list
5. Confirm deletion

### Add Multiple Records to One Plot
1. Click the occupied lot
2. Click "Edit Record"
3. Scroll down to the form
4. Enter a new deceased person's information
5. Click "Add Record"

The lot will now show multiple names!

## File Locations

```
GraveTrack/
├── cemetery_map_v2.php          ← Main page (open this)
├── edit_burial_record.php       ← Edit/add records
├── api/
│   └── get_lot_details.php      ← Backend API
├── Database/
│   ├── db_migration_phases.sql  ← Run this first
│   └── db_connector.php
└── CEMETERY_MAP_README.md       ← Full documentation
```

## Plot Code Format Reference

| Phase | Block | Code Example |
|-------|-------|--------------|
| Phase 1 | A-I | `A-1`, `I-10` |
| Phase 2 | T-Z | `T-1`, `Z-10` |
| Phase 3 | AA (Sections 1-4) | `AA-S1-L1` to `AA-S4-L50` |

## Troubleshooting

### "Database Connection Failed"
- Check `Database/db_connector.php` credentials
- Ensure MySQL is running
- Verify database name is correct

### Phases Not Showing
- Run the migration script first
- Check the `phases` table has 3 rows
- Verify blocks are assigned correct phase_id

### Lots Not Colored
- Click on a lot to add a record
- The color updates after adding deceased data
- Refresh the page if needed

### Can't Edit a Record
- Make sure you're clicking the "Edit Record" button
- Check browser console for JavaScript errors

## Next Steps

1. Populate your cemetery data by adding burial records
2. Use the search feature to find specific deceased
3. Track payment statuses for better management
4. Monitor Phase 3 apartment section occupancy

## Support Files

- **Full Documentation**: `CEMETERY_MAP_README.md`
- **Database Schema**: `Database/gravetrack_db.sql`
- **Migration Script**: `Database/db_migration_phases.sql`

Enjoy using GraveTrack Cemetery Management System!
