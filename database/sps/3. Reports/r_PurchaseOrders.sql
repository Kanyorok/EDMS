----Modified by Joe Muchai on 12/02/26
CREATE or alter   PROC [dbo].[r_PurchaseOrders] @FromDate DATETIME = NULL,
                                              @ToDate DATETIME =NULL
AS
BEGIN

    CREATE TABLE #PurchaseOrders

    (
        OrderNo    VARCHAR(20),
        GrvNo      VARCHAR(20),
      
  AccountID  VARCHAR(200),
        --Description
        OrderDate  DATE,
        OrdTotExcl MONEY,
        OrdTotIncl MONEY,
        CreatedBy  VARCHAR(20)

    )

    INSERT INTO #PurchaseOrders

    SELECT D.OrderNo,
           GR.GRNID AS [GRN NO.],
           S.ID
 as AccountID,
           D.OrderDate,
           D.OrdTotExcl,
           D.OrdTotIncl,
           U.UserID

    FROM t_Orders D
             JOIN t_Users U ON U.ID = D.CreatedBy
             JOIN t_Suppliers S ON S.ID = D.AccountID
             JOIN t_GoodsReceipts GR ON GR.POID = D.ID


    SELECT * FROM #PurchaseOrders

END

-- GO

-- exec r_PurchaseOrders 

