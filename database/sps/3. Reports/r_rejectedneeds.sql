
Create  OR ALTER   procedure r_rejectedneeds
                               
	@FromDate DateTime,                                 
	@ToDate DateTime
                                    
AS
                                                               
BEGIN
                                                            
	Create table #rejectedneeds(
                                    
	ID int identity(1,1),                                           
	NeedID Varchar(100),                                            
	ItemName Varchar(100),                                          
	Branch   Varchar(100),                                          
	Quantity  Int,                                                  
	CreatedOn Datetime
                                              
	);
                                                              
Insert into #rejectedneeds(
                                      
	NeedID,                                                         
	ItemName,                                                       
	Branch,                                                         
	Quantity,                                                       
	CreatedOn
	)
                                                      

                                                                 
select D.NeedID,                                                 
	   IT.ItemName,                                                 
	   B.Name AS BranchName,                                        
	   D.RequestedQty,                                              
	   D.CreatedOn
                                                  
	FROM
                                                            
	t_DepartmentNeeds AS D                                          
	INNER JOIN t_items AS IT ON D.ItemID=IT.Id                      
	INNER JOIN t_Branches AS B ON D.BranchID=B.Id 
                  
WHERE D.Status='r' AND  D.CreatedOn BETWEEN @FromDate AND @ToDate


                                                                 
	  Select * from #rejectedneeds                               
                              
								                                                                 
END

--GO
--EXEC r_rejectedneeds @FromDate = '2020-01-01', @ToDate = '2026-12-31'
