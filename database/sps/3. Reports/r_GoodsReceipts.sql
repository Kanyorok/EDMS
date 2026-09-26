
----Modified by Joe Muchai on 16/03/26
                               
CREATE OR ALTER   PROC [dbo].[r_GoodsReceipts] 
                              
                                                                   
@FromDate SMALLDATETIME =NULL,
                                       
@ToDate SMALLDATETIME = NULL
                                                                   
AS
                                                                   
BEGIN
                                                                
    CREATE TABLE #GoodsReceipts
                                      
    (
                                                                
        GRNID        VARCHAR(20),
                                    
        POID         VARCHAR(20),
                                    
        SupplierName VARCHAR(100),
                                   
        StoreName      VARCHAR(200),
                                 
        RecievedBy   VARCHAR(50),
                                    
        ItemName       VARCHAR(400),
                                            
        POQTY        FLOAT,
                                          
        ReceivedQTY  FLOAT,
                                          
        TransferTo   VARCHAR(50),
                                    
        CreatedOn    DATE,
                                           
        CreatedBy    VARCHAR(50)
                                     
    )
                                                                

                                                                     
    INSERT INTO #GoodsReceipts
                                       
    SELECT G.GRNID as [GRN Reference],
                                                  
            O.OrderNo as [P.O Number],
                                                   
           TP.ThirdPartyName as [Supplier Name],
                     
           st.StoreName as [Store Name],
                             
           U2.Name as [Received By],
                                    
           I.ItemName as [Item Name], 
                                                
           G.POQTY as [P.O Qty],
                                                  
           G.ReceivedQTY as [Received Qty],
                                            
           U3.Name AS [Transfer To],
                                    
           G.CreatedOn as [Created On],
                                              
           U.Name  AS [Created By]
                                      
                                                                
    FROM t_GoodsReceipts G
                                           
            LEFT JOIN t_SupplierMaster S ON S.Id = G.SupplierId

            LEFT JOIN t_orders O ON O.Id = G.POID
      
            LEFT JOIN t_Users U ON U.Id = G.CreatedBy
                
            LEFT JOIN t_Users U2 ON U2.Id = G.ReceivedBy
             
            LEFT JOIN t_Users U3 ON U3.Id = G.TransferTo
             
            JOIN t_ThirdParties TP ON TP.ID= S.ThirdPartyId
          
            LEFT JOIN t_Stores ST ON ST.Id = G.StoreId
               
               LEFT JOIN t_Items I ON I.Id = G.ItemNo

                                                                     
    WHERE (@FromDate IS NULL OR G.CreatedOn >= @FromDate)
            
      AND (@ToDate IS NULL OR G.CreatedOn < DATEADD(DAY, 1, @ToDate))
                                                              
    SELECT * FROM #GoodsReceipts
                                                                  
    END
                                                              
GO



