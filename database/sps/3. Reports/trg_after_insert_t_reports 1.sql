CREATE OR ALTER TRIGGER [dbo].[trg_after_insert_t_reports]
    ON [dbo].[t_Reports]
    AFTER INSERT
AS
BEGIN
    SET NOCOUNT ON;

    -- Create the new permission ONLY if it doesn't exist yet
    INSERT INTO t_permissions (name, guard_name, created_at, updated_at, ModuleId)
    SELECT 
        'report_' + REPLACE(i.name, ' ', '_'), 
        'web', 
        GETDATE(), 
        GETDATE(), 
        i.moduleid
    FROM inserted i
    WHERE NOT EXISTS (
        SELECT 1 FROM t_permissions p 
        WHERE p.name = 'report_' + REPLACE(i.name, ' ', '_')
    );

    -- Sync back to t_Reports ONLY if the permissionname is different
    -- This prevents unnecessary trigger re-firing
    UPDATE r
    SET r.permissionname = 'report_' + REPLACE(i.name, ' ', '_')
    FROM t_Reports r
    INNER JOIN inserted i ON r.id = i.id
    WHERE r.permissionname IS NULL 
       OR r.permissionname <> 'report_' + REPLACE(i.name, ' ', '_');
END;

