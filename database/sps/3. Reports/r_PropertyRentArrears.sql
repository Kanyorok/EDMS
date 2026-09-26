--Created by Joe Muchai on 24/04/26
CREATE OR ALTER PROCEDURE [dbo].[r_PropertyRentArrears]
    @PaymentStatus NVARCHAR(MAX) = 'Underpayment,Overpayment' 
AS
BEGIN
    SET NOCOUNT ON

   
    CREATE TABLE #SelectedStatuses (StatusVal VARCHAR(100))
    
  
    INSERT INTO #SelectedStatuses
    SELECT value FROM STRING_SPLIT(@PaymentStatus, ',')

    CREATE TABLE #PropertyRentArrears (
        [No.]            INT,  
        [Lease Number]   VARCHAR(200),
        MonthlyRent      MONEY,
        [Invoice Number] VARCHAR(200),
        RentAmount       MONEY,
        Arrears          MONEY,
        [Payment Status] VARCHAR(200),
        [Tenant Name]    VARCHAR(200),
        [Tenant Email]   VARCHAR(200),
        [Property Name]  VARCHAR(200),
        [Property Code]  VARCHAR(200)
    )

    INSERT INTO #PropertyRentArrears
    SELECT 
        ROW_NUMBER() OVER (ORDER BY LC.LeaseNumber ASC) AS [No.], 
        LC.LeaseNumber AS [Lease Number],
        LC.MonthlyRent,
        RI.InvoiceNumber,
        RI.RentAmount,    
        (LC.MonthlyRent - RI.RentAmount) AS Arrears,
        CASE 
            WHEN (LC.MonthlyRent - RI.RentAmount) > 0 THEN 'Underpayment'
            WHEN (LC.MonthlyRent - RI.RentAmount) < 0 THEN 'Overpayment'
        END AS PaymentStatus,
        TP.Thirdpartyname AS [Tenant Name],
        TP.Email AS [Tenant Email],
        PR.PROPERTYNAME AS [Property Name],
        PR.PropertyCode AS [Property Code]
    FROM t_LeaseCreation LC
    JOIN t_RentInvoice RI ON LC.Id = RI.Lease
    JOIN t_TenantMaintenance TM ON LC.Tenant = TM.Id
    JOIN t_ThirdParties TP ON TM.ThirdPartyId = TP.ID
    JOIN t_PropertyRegistry PR ON LC.PropertyID=PR.ID
    JOIN t_FinanceInvoices FI ON FI.InvoiceNumber=RI.InvoiceNumber
    WHERE TP.DeletedOn IS NULL 
      AND (LC.MonthlyRent - RI.RentAmount) <> 0
      AND FI.ApprovalStatus ='posted'
    
      AND (
            CASE 
                WHEN (LC.MonthlyRent - RI.RentAmount) > 0 THEN 'Underpayment'
                WHEN (LC.MonthlyRent - RI.RentAmount) < 0 THEN 'Overpayment'
            END 
          ) IN (SELECT StatusVal FROM #SelectedStatuses)

    SELECT * FROM #PropertyRentArrears ORDER BY [No.]

    DROP TABLE #PropertyRentArrears
    DROP TABLE #SelectedStatuses

    SET NOCOUNT OFF
END

--GO
--EXEC [dbo].[r_PropertyRentArrears] @PaymentStatus = 'Underpayment,Overpayment'