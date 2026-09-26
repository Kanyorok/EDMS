
CREATE   Or ALter     PROCEDURE  [dbo].[r_WonLeads]
(
@FromDate  DateTime =NULL,
@ToDate   DateTime =NULL
)
AS
BEGIN 
SET NOCOUNT ON;

Select 
L.Name + ' ' + L.OtherNames AS FullName,
L.Email,
L.Phone,
case
When L.Type='I' then 'Individual'
when L.Type='c' then 'Corporate' end As Type,

U.Name As RelationshipManager,
I.Name  AS Location
From t_leads  L 
LEFT JOIN  t_Users U 
ON L.RelationshipManagerID=U.Id
LEFT JOIN t_Localities I
ON  L.LocationID=I.id

Where L.Status='wo'
--AND (@FromDate IS NULL OR L.CreatedOn >= @FromDate)
--AND (@ToDate IS NULL OR L.CreatedOn < DATEADD(DAY, 1, @ToDate))

SET NOCOUNT OFF;
END;




