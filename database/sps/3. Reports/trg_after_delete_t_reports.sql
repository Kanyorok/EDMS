CREATE OR ALTER TRIGGER [dbo].[trg_after_delete_t_reports]
    ON [dbo].[t_Reports]
    AFTER DELETE
AS
BEGIN
    SET NOCOUNT ON;

    DELETE p
    FROM t_permissions p
    INNER JOIN deleted d ON p.name = 'report_' + REPLACE(d.name, ' ', '_');
END;