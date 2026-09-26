CREATE OR ALTER PROC [dbo].[R_InventoryReviewlist] 
    @FromDate DATETIME = NULL,
    @ToDate DATETIME = NULL,
    @BranchID VARCHAR(max) = NULL, 
    @Source   VARCHAR(max) = NULL  
AS
BEGIN
    SET NOCOUNT ON;

    
    CREATE TABLE #InventoryReviewlist
    (
        InventoryholdID NVARCHAR(200),
        Item            VARCHAR(100),
        FromBranch      VARCHAR(100),
        Store           VARCHAR(100),
        Quantity        DECIMAL(18, 2),
        Reason          VARCHAR(200),
        Source          VARCHAR(100),
        Status          VARCHAR(100),
        Remarks         VARCHAR(200),
        CreatedBy       VARCHAR(100),
        CreatedOn       DATETIME
    )

   
    INSERT INTO #InventoryReviewlist
    SELECT 
        I.InventoryHoldID,
        IT.ItemName    AS Item,
        B.Name         AS FromBranch,
        ST.StoreName        AS Store,      
        I.Quantity,
        C.Description  AS Reason,
        CT.Description AS Source, 
        CD.Description AS Status,
        I.Remarks,                    
        U.Name         AS CreatedBy,
        I.CreatedOn
    FROM t_InventoryHold I
    INNER JOIN t_users U ON U.ID = I.CreatedBy
    INNER JOIN t_Items IT ON IT.ID = I.ItemID
    INNER JOIN t_branches B ON B.ID = I.BranchID
    INNER JOIN t_codedetails C ON C.ID = I.Reason
    INNER JOIN t_codedetails CT ON CT.ID = I.Source 
    INNER JOIN t_CodeDetails CD ON CD.Value = I.Status
    LEFT JOIN t_Stores ST ON ST.ID = I.Store 
    WHERE 
    
        I.DeletedOn IS NULL 
        
     
        AND (@FromDate IS NULL OR I.CreatedOn >= @FromDate)
        AND (@ToDate IS NULL OR I.CreatedOn < DATEADD(DAY, 1, @ToDate))
        
    
        AND (
             @BranchID IS NULL 
             OR @BranchID = 'ALL' 
             OR @BranchID = ''
             OR B.Name IN (SELECT TRIM(value) FROM STRING_SPLIT(@BranchID, ','))
            )
        
    
        AND (
             @Source IS NULL 
             OR @Source = 'ALL' 
             OR @Source = ''
             OR CAST(I.Source AS VARCHAR) IN (SELECT TRIM(value) FROM STRING_SPLIT(@Source, ','))
            )

    SELECT * FROM #InventoryReviewlist ORDER BY CreatedOn DESC

    DROP TABLE #InventoryReviewlist
END
--go

--EXEC [dbo].[R_InventoryReviewlist] 
--@Source = 84




