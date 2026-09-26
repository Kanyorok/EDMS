CREATE OR ALTER  PROCEDURE [dbo].[r_PriceListing]

AS
BEGIN
    SET NOCOUNT ON

    CREATE TABLE #PriceListing
    (
        ID            Varchar(200),
        PriceID       Varchar(200),
        ItemID        Varchar(200),
        UOM           Varchar(200),
        ActualPrice   Money,
        CurrencyCode  Varchar(200),
        EffectiveFrom Date,
        EffectiveTo   Date,
        CreatedOn     Date,
        ModifiedBy    Varchar(200),

        ModifiedOn    Date
    )

    INSERT INTO #PriceListing
    (ID,
     PriceID,
     ItemID,
     UOM,
     ActualPrice,
     CurrencyCode,
     EffectiveFrom,
     EffectiveTo,
     CreatedOn,
     ModifiedBy,
     ModifiedOn)
    SELECT PL.ID,
           PL.PriceID,
           I.ItemName as [ItemID],
           --PL.EstimatedPrice,
           PU.Name    as UOM,
           PL.ActualPrice,
           PL.CurrencyCode,
           PL.EffectiveFrom,
           PL.EffectiveTo,

           PL.CreatedOn,
           U.Name     AS ModifiedBy,
           PL.ModifiedOn
    FROM t_Pricing as PL
             JOIN t_UOM as PU ON PL.ID = PU.ID
             JOIN t_users as U ON U.ID = PL.ID
             Join t_Items as I on I.Id = pl.ItemID
  
    ORDER BY PL.ItemID

    SELECT * FROM #PriceListing (NOLOCK)

    SET NOCOUNT OFF
END
--GO
--EXEC [r_PriceListing]

