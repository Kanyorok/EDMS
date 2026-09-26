Create  or alter  procedure r_CreditorInvoices 
	@FromDate Date = null,
	@ToDate Date = null,
	@SupplierID	Varchar(200) =null,
	@CurrencyID Nvarchar(100) =null
	
  
AS  
BEGIN  
create table #CreditorInvoices  
	(  
		SupplierName		Varchar(200),  
		InvoiceNumber		Nvarchar(200),  
		Currency 			Nvarchar(100),  
		ExchangeRate		Int,  
		POReference 		Nvarchar(200), 
		GRNReference		Nvarchar(200),  
		InvoiceDate 		Date,  
		Amount  			Money,  
		DueDate  			Date,  
		AmountPaid			Money,
		PaidDate			Date 
)  
  
Insert into #CreditorInvoices  
select  
  
	TP.ThirdPartyName as [SupplierID],  
	FE.InvoiceNumber,  
	C.Code as [CurrencyID],  
	FE.ExchangeRate,  
	G1.POID as [POReference],  
	G.GRNID as[GRNReference], 
	FE.InvoiceDate,  
	FE.InvoiceAmount,  

	FE.Duedate, 
	SUM(CASE WHEN T.DRCR = 'CR' THEN T.Amount ELSE 0 END) AS AmountPaid,
	 MAX(T.TransactionDate) AS PaidDate
	--CASE
	--When T.DRCR='CR' Then T.Amount
	--Else Null
	--End As AmountPaid
	--MAX(T.TransactionDate) AS TransactionDate,
	
  
from t_FinanceInvoiceEntry FE  
LEFT JOIN t_FinancialTransactions T
        ON FE.InvoiceNumber = T.ReferenceNumber
       AND T.DRCR = 'CR' 
JOIN t_Currencies C ON FE.CurrencyID = C.ID  
JOIN t_GoodsReceipts G ON FE.GRNReference=G.ID
JOIN t_GoodsReceipts G1 ON FE.POReference=G1.ID
JOIN t_ThirdParties TP ON FE.SupplierID =TP.ID  

	 WHERE 
	 (@FromDate IS NULL OR T.Transactiondate >=@FromDate)
		 AND (@ToDate IS NULL OR T.Transactiondate < DATEADD(DAY, 1, @ToDate))         
		 AND (@SupplierID IS NULL OR  @SupplierID = 'ALL' OR
		TP.ThirdPartyName IN (SELECT value FROM STRING_SPLIT(@SupplierID , ','))  )  
		 AND (@CurrencyID IS NULL OR  @CurrencyID = 'ALL' OR
		C.Code IN (SELECT value FROM STRING_SPLIT(@CurrencyID , ','))  ) 
		
         
GROUP BY  
    TP.ThirdPartyName,  
    FE.InvoiceNumber,  
    C.Code ,  
    FE.ExchangeRate,  
    G1.POID,  
    G.GRNID,  
    FE.InvoiceDate,  
    FE.InvoiceAmount,  
    FE.Duedate
	

	
select * from #CreditorInvoices  
end  

 
	