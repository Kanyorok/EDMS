--Modified by Joe Muchai on 26/03/26
CREATE OR ALTER PROCEDURE [dbo].[r_PropertyType]
AS
BEGIN
    SET NOCOUNT ON


    CREATE TABLE #PropertyType
    (
        [No.]       INT,          
        Name        VARCHAR(200),
        Category    VARCHAR(200),
        Description VARCHAR(200),
        CreateOn    DATE,
        CreatedBy   VARCHAR(200)
    )


    INSERT INTO #PropertyType
    (
        [No.],
        Name,
        Category,
        Description,
        CreateOn,
        CreatedBy
    )
    SELECT 
        ROW_NUMBER() OVER (ORDER BY P.PropertyTypeName ASC) AS [No.], 
        P.PropertyTypeName,
        CM.Name as Category,
        P.Description,
        P.CreatedOn,
        U.Name as CreatedBy
    FROM t_propertytype AS P
    JOIN t_Users AS U ON P.Id = U.ID 
    JOIN t_CategoryMaster AS CM ON P.Id = CM.ID

    SELECT * FROM #PropertyType ORDER BY [No.]


    DROP TABLE #PropertyType

    SET NOCOUNT OFF
END

--Go
--exec [r_PropertyType]