--Modified by Joe Muchai on 26/3/2026
create  OR ALTER proc [dbo].[r_ScheduleLease] 
@FromDate Datetime = null,
@ToDate Datetime=null,
@PaymentFrequency Varchar(100)=null
As
Begin
    Create Table #ScheduleLease

    (	
		[No.]       INT,   
        LeaseNumber      Nvarchar(200),
        TenantName       Varchar(200),
        PropertyName     Varchar(200),
        PaymentFrequency Varchar(100),
        StartDate        Date,
        EndDate          Date,
        BaseRent         Money,
        ServiceCharge    Money,
        ParkingFee       Money,
        OtherCharges     Money,
        CreatedBy        Varchar(100),
        CreatedOn        Date
    )
    Insert Into #ScheduleLease
    SELECT 
		ROW_NUMBER() OVER (ORDER BY P.PropertyName ASC) AS [No.],
		L.LeaseNumber  as LeaseNumber,
			TP.ThirdPartyName as TenantName,
           P.PropertyName as PropertyId,
           C.Description  as PaymentFrequency,
           S.StartDate,
           S.EndDate,
           S.BaseRent,
           S.ServiceCharge,
           S.ParkingFee,
           S.OtherCharges,
           U.Name         as CreatedBy,
           S.CreatedOn
    From t_ScheduleLease S
             Join t_LeaseCreation L ON L.ID = S.LeaseNumber
             Join t_TenantMaintenance T ON T.ID = S.Id
             Join t_CodeDetails C ON C.ID = S.PaymentFrequency
             Join t_PropertyRegistry P ON P.ID = S.Id
             Join t_users U ON U.ID = S.CreatedBy
			 JOIN t_ThirdParties TP on TP.Id=T.ThirdPartyId
    Where (@FromDate IS NULl OR S.CreatedOn >= @FromDate)
      AND (@ToDate IS NULL OR S.CreatedOn < DATEADD(DAY, 1, @ToDate))

      AND (@PaymentFrequency IS NULL OR @PaymentFrequency = 'ALL' OR
           C.Description IN (SELECT value FROM STRING_SPLIT(@PaymentFrequency, ',')));
    select * from #ScheduleLease
END
--GO
--EXEC r_ScheduleLease

