CREATE  or alter  PROCEDURE [dbo].[r_StockTake](
    @FromDate SMALLDATETIME = NULL,
    @ToDate SMALLDATETIME = NULL
)
AS
BEGIN
    SET NOCOUNT ON
    CREATE TABLE #StockTake
    (
        Branch    VARCHAR(200),
        StoreName     VARCHAR(200),
        CountedBy   VARCHAR(200),
        CountedDate      DATETIME,
		ActualQuantity VARCHAR(200),
		   CountedQuantity VARCHAR(200),
		   Item VARCHAR(200),
        PostedBy  VARCHAR(200),
        PostedOn  DATETIME
    )

    INSERT INTO #StockTake
    (Branch,
     StoreName,
     CountedBy,
     CountedDate,
	 ActualQuantity,
	 CountedQuantity,
	 Item,
	 PostedBy,
     PostedOn)
   
			 SELECT 
			 BR.Name AS Branch,
           K.StoreName,
           R.NAme as CountedBy,
           ST.CountDate,
		   ActualQuantity,
		   CountedQuantity,
		   I.ItemName AS Item,
		   U.Name AS PostedBy,
           ST.CreatedOn AS PostedOn
    FROM t_StockTake ST
             JOIN t_Branches BR ON ST.BRANCHID = BR.ID
             JOIN t_Users U on U.ID = ST.CREATEDBY
             JOIN t_Users R on R.ID = ST.CountedBy
             JOIN t_Stores K on K.Id = st.storeid
			 JOIN t_StockTakeLines SL on SL.Id = ST.Id
			 Join t_Items I on I.Id = SL.id
    WHERE (@FromDate IS NULL OR ST.CreatedOn >= @FromDate)
      AND (@ToDate IS NULL OR ST.CreatedOn < DATEADD(DAY, 1, @ToDate))
    SELECT * FROM #StockTake

    DROP TABLE #StockTake

    SET NOCOUNT OFF
END

--GO

--exec r_StockTake


