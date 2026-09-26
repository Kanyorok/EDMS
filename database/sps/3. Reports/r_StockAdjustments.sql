CREATE OR ALTER PROC [dbo].[r_StockAdjustments]
@FromDate smalldatetime = NULL,
@ToDate smalldatetime = NULL

AS
BEGIN
    SET NOCOUNT ON;

    CREATE TABLE #StockAdjustments
    (
        AdjustmentID    VARCHAR(200),
        AdjustmentDate SMALLDATETIME,
        Branch          VARCHAR(200),
        AdjustedBy      VARCHAR(200),
        Status          VARCHAR(200),
        CreatedBy       VARCHAR(200),
        CreatedOn       SMALLDATETIME
    );

    INSERT INTO #StockAdjustments
    (AdjustmentID,
     AdjustmentDate,
     Branch,
     AdjustedBy,
     Status,
     CreatedBy,
     CreatedOn)
    SELECT SA.AdjustmentID,
           SA.AdjustmentDate,
           B.Name          AS Branch,
           U1.Name         AS AdjustedBy,
           CD.Description  AS Status,
           U2.Name         AS CreatedBy,
           SA.CreatedOn
    FROM t_StockAdjustments SA
             JOIN t_Branches B ON B.ID = SA.Branch
             -- FIX 1: Join Status to Value (nvarchar to nvarchar)
             LEFT JOIN t_CodeDetails CD ON CD.Value = SA.Status AND CD.CodeID = 'RequisitionStatus'
             -- FIX 2: Use TRY_CAST for numeric ID join (safely handles strings)
             LEFT JOIN t_CodeDetails C ON C.ID = TRY_CAST(SA.Status AS BIGINT) AND C.CodeID = 'AdjustmentReason'
             JOIN t_Users U1 ON U1.ID = SA.AdjustedBy
             JOIN t_Users U2 ON U2.ID = SA.CreatedBy
    WHERE (@FromDate IS NULL OR SA.AdjustmentDate >= @FromDate)
      AND (@ToDate IS NULL OR SA.AdjustmentDate < DATEADD(DAY, 1, @ToDate))

    SELECT * FROM #StockAdjustments
    DROP TABLE #StockAdjustments
END
--GO
--exec [r_StockAdjustments]


