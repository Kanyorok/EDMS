CREATE OR ALTER TRIGGER [dbo].[trg_after_update_t_reports]
    ON [dbo].[t_Reports]
    AFTER UPDATE
AS
BEGIN
    SET NOCOUNT ON;

    -- 1. If the update was just the trigger syncing the permissionname, EXIT.
    -- This is the most efficient way to stop the recursion.
    IF (UPDATE(permissionname) AND NOT UPDATE(name) AND NOT UPDATE(ModuleId))
        RETURN;

    -- 2. Update existing permission
    UPDATE p
    SET 
        p.name = 'report_' + REPLACE(i.name, ' ', '_'),
        p.ModuleId = i.ModuleId,
        p.updated_at = GETDATE()
    FROM t_permissions p
    INNER JOIN deleted d ON p.name = 'report_' + REPLACE(d.name, ' ', '_')
    INNER JOIN inserted i ON d.id = i.id
    WHERE NOT EXISTS (
        SELECT 1 FROM t_permissions p2 
        WHERE p2.name = 'report_' + REPLACE(i.name, ' ', '_')
        AND p2.id <> p.id
    );

    -- 3. Sync back to t_Reports
    -- The WHERE clause ensures that if permissionname is already correct, 
    -- we don't update again, preventing the recursion level error.
    UPDATE r
    SET r.permissionname = 'report_' + REPLACE(i.name, ' ', '_')
    FROM t_Reports r
    INNER JOIN inserted i ON r.id = i.id
    WHERE r.permissionname <> 'report_' + REPLACE(i.name, ' ', '_')
       OR r.permissionname IS NULL;
END;

