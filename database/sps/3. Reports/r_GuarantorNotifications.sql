GO
/****** Object:  StoredProcedure [dbo].[r_GuarantorNotifications]    Script Date: 08/04/2026 14:31:12 ******/
GO
GO

CREATE OR ALTER procedure   [dbo].[r_GuarantorNotifications] 
(

@FromDate  DateTime =NUll,
@ToDate  DateTime =Null,
@SourceID  Nvarchar =NUll 
)
AS
Begin 
Set NOCount ON ;

CREATE  TABLE #Guarantornotifications(

ClientID INT,
GuarantorName  VARCHAR (1000),
PhoneNumber NVARCHAR(1000) ,
Message   VARCHAR (1000),
AccountNumber NVARCHAR (1000),
SmsStatus  VARCHAR (100),
MessageSentOn DateTime  
)

Insert into #Guarantornotifications (

ClientID,
GuarantorName,
PhoneNumber,
Message,
AccountNumber,
SmsStatus,
MessageSentOn
)


SELECT
    e.ClientId,
    e.name,
    f.Recipient,
    f.MessageBody,
    f.SourceID,
	case 
	when f.Status='F' then 'Failed'
	when f.Status='P' then 'Pending'
	when f.Status='s' then 'sent '
   END ,
    f.Dated
FROM syn_t_AdvancesReport a
JOIN syn_t_AccountCustomer b ON a.AccountID = b.AccountId
JOIN syn_t_AccountGuarantor d ON a.accountid = d.accountid
JOIN syn_t_Client e ON d.guarantorid = e.clientid
JOIN t_sms f ON e.clientid = f.ClientID AND f.source = 'LoanId' AND d.accountid = f.sourceid
And f.Dated>=@FromDate
And f.Dated < DateAdd(Day,1,@ToDate)
Order BY f.Dated Desc;

select * from #Guarantornotifications
set NOcount Off

END;
 

 --Select * from t_SMS

 --select *  from t_users 

 --exec r_GuarantorNotifications
