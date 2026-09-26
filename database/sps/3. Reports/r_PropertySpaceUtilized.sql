--Created by Joe Muchai on 21/04/26
CREATE OR ALTER PROCEDURE [dbo].[r_PropertySpaceUtilized]
AS
BEGIN
    SET NOCOUNT ON


    CREATE TABLE #PropertySpaceUtilized
    (
        [No.]       INT,          
        PropertyName        VARCHAR(200),
        PropertyCode VARCHAR(200),
        [Owner]    VARCHAR(200),
        [LRNo.]   VARCHAR(200),
        [PlotSize in sqm]  VARCHAR(200),
        [Block Name] VARCHAR(200),
        [Description]        VARCHAR(200),
        [Block Size in sqft] VARCHAR(200)
    )


    INSERT INTO #PropertySpaceUtilized
    (
        [No.],
        PropertyName,
        PropertyCode,
        [Owner],
        [LRNo.],
        [PlotSize in sqm],
        [Block Name],
        [Description],
        [Block Size in sqft]
    )
    SELECT 
        ROW_NUMBER() OVER (ORDER BY PR.PropertyName ASC) AS [No.], 
        PR.PropertyName,
        PR.PropertyCode,
        PR.Owner as [Owner],
        PR.LRNumber,
        PR.PlotSize,
        PB.BlockName,
        PB.Description,
        PB.SizeInSqFt

    FROM  t_PropertyRegistry PR  
    JOIN t_PropertyBlock PB on PR.id=PB.PropertyID
    where PR.DeletedOn is null 

    SELECT * FROM #PropertySpaceUtilized ORDER BY [No.]


    DROP TABLE #PropertySpaceUtilized

    SET NOCOUNT OFF
END

--Go
--exec [r_PropertySpaceUtilized]