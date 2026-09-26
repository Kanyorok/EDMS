CREATE OR ALTER PROC [dbo].[R_ItemCatalogue](
    @ItemCategory VARCHAR(200) = NULL
)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT 
        C.ItemCode AS UniqueCode,
        C.ItemName,
        C.ItemDescription,
        --        ISNULL(CD.Description, 'Unknown') AS [Item Type], 
        IC.Name AS Category,
        UM.Code AS UOM,
        '' AS Currency,
        C.ItemPrice AS UnitPrice,
        U.Name AS CreatedBy,
        C.ModifiedBy,
        C.DeletedBy
    FROM t_Items C
    INNER JOIN t_Users U ON C.CreatedBy = U.Id
    INNER JOIN t_ItemCategories IC ON CAST(IC.Id AS VARCHAR(50)) = CAST(C.Category AS VARCHAR(50))
    INNER JOIN t_UOM UM ON UM.Id = C.UOM
    -- FIX: Convert the ID (386) to a String to match the ItemType column exactly
    LEFT JOIN t_CodeDetails CD ON CAST(C.ItemType AS VARCHAR(50)) = CAST(CD.ID AS VARCHAR(50))
                               AND CD.CodeID = 'ItemTypeStatus'
    WHERE 
        (@ItemCategory IS NULL OR @ItemCategory = 'ALL')
        OR CAST(C.Category AS VARCHAR(50)) IN (SELECT value FROM STRING_SPLIT(@ItemCategory, ','))
END

--go

--exec [R_ItemCatalogue]


