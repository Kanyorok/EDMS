--Modified by Joe Muchai on 27/03/2026
Create or alter  PROC [dbo].[R_BankAssuranceReferrals] 

@StartDate DateTime=NULL ,
@EndDate DateTime=NULL

AS
BEGIN
    SET NOCOUNT ON;
Create table #BankAssuranceReferrals (
  PINNo Nvarchar(100),
  ClientName Nvarchar(100),
  ReferralDate  Date,
  ClientPhone NVARCHAR(100),
  PhoneNumber Nvarchar(100),
  ClientEmail Nvarchar(100)


)
Insert Into #BankAssuranceReferrals
Select

  TP.TaxPIN as [PIN No.],
  TP.ThirdPartyName as [Client Name],
  BR.ReferralDate,
  Br.ReferredBy,
  TP.Phone as [Client Phone],
  TP.Email as  [Client Email]

from t_BancassuranceReferrals BR 
	JOIN t_BancassuranceCustomers BC on BC.Id=BR.ClientId
	JOIN t_ThirdParties TP on TP.id=BC.ThirdPartyId

Where BR.createdon between @StartDate and @EndDate

Select * from #BankAssuranceReferrals

Drop Table #BankAssuranceReferrals
END

--go
--exec R_BankAssuranceReferrals


