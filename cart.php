<?php
include __DIR__ . "/cartfuncties.php";
include __DIR__ . "/header.php";

$Query = "
           SELECT SI.StockItemID, SI.StockItemName, SI.MarketingComments, TaxRate, RecommendedRetailPrice,
           ROUND(SI.TaxRate * SI.RecommendedRetailPrice / 100 + SI.RecommendedRetailPrice,2) as SellPrice,
           QuantityOnHand,
           (CASE WHEN (RecommendedRetailPrice*(1+(TaxRate/100))) > 50 THEN 0 ELSE 6.95 END) AS SendCosts,
           (SELECT ImagePath FROM stockitemimages WHERE StockItemID = SI.StockItemID LIMIT 1) as ImagePath,
           (SELECT ImagePath FROM stockgroups JOIN stockitemstockgroups USING(StockGroupID) WHERE StockItemID = SI.StockItemID LIMIT 1) as BackupImagePath
           FROM stockitems SI
           JOIN stockitemholdings SIH USING(stockitemid)
           JOIN stockitemstockgroups USING(StockItemID)
           JOIN stockgroups ON stockitemstockgroups.StockGroupID = stockgroups.StockGroupID
           WHERE 'iii' NOT IN (SELECT StockGroupID from stockitemstockgroups WHERE StockItemID = SI.StockItemID)
           GROUP BY StockItemID";

$Statement = mysqli_prepare($databaseConnection, $Query);
mysqli_stmt_execute($Statement);
$ReturnableResult = mysqli_stmt_get_result($Statement);
$ReturnableResult = mysqli_fetch_all($ReturnableResult, MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Winkelwagen</title>
    <link rel="stylesheet" href="public/css/style.css" type="text/css">
</head>
<body>
    <div id="cartBackground">
  <?php
$cart = getCart();
if ($cart != null) {
    print('<div id="titleCart">');          //Als de winkelmand is gevuld, show dan button verder winkelen en afrekenen
    print('<h1 id="titleText">Winkelmand</h1>');
    print('<form action="index.php">');
    print('<button id="verderWinkelenKnop">Verder winkelen</button></form>');
    print('<button id="AfrekenenKnop">Afrekenen</button>');
    print('</div>');
}

$totaalprijs = 0;
$hoogsteverzending = 0;
foreach($cart as $artikelnummer => $aantalartikel)
{
    if($aantalartikel > 0){
    $StockItem = getStockItem($artikelnummer, $databaseConnection);
    $StockItemImage = getStockItemImage($artikelnummer, $databaseConnection);
    print("<div class='productCard'>");
    print("<div class='flex-container leftProductCard'>");
    foreach ($ReturnableResult as $row) {
            if ($artikelnummer == $row["StockItemID"]) {
                if(str_replace(" ", "%20",strtolower($row['ImagePath'])) == "" OR str_replace(" ", "%20",strtolower($row['ImagePath'])) == null){
                      $imagepath = str_replace(" ", "%20",strtolower($row['BackupImagePath']));
                      print ("<img style='float:left;width:110px;height:110px;margin-top:5px;margin-left:5px;'src="."public/stockgroupimg/".$imagepath.">");
               }else{
                      $imagepath = str_replace(" ", "%20",strtolower($row['ImagePath']));
                      print ("<img class='productImage'src="."public/stockitemimg/".str_replace(" ", "%20",strtolower($StockItemImage[0]['ImagePath'])).">");
               }
            }
    }
    print ("<h5 class='productName'>".$StockItem['StockItemName']."</h5>");
    print("<h5 class='productStockAmount'>".$StockItem['QuantityOnHand']."</h5>");
    print("</div>");
    print("<div class='rightProductCard'>");
    print('<form method="post">
    <div class="upperRightProductCard">
    <input type="number" name="stockItemID" value="print($artikelnummer)" hidden>
    <input type="number" name="aantalvanartikelen" value='.$cart[$artikelnummer].' class="rangeInputForm" > ');
    if (isset($ReturnableResult) && count($ReturnableResult) > 0) {
        foreach ($ReturnableResult as $row) {
            if ($artikelnummer == $row["StockItemID"]) {
                $totaalprijs += $cart[$artikelnummer] * sprintf('%0.2f', berekenVerkoopPrijs($row['RecommendedRetailPrice'], $row['TaxRate']));
                print("<h6 class='prijsWeergave'> €". $cart[$artikelnummer] * sprintf('%0.2f', berekenVerkoopPrijs($row['RecommendedRetailPrice'], $row['TaxRate']))."</h6>");
                //print("<h1 style='color:black;'>".$row['MarketingComments']."</h1>");
                if(str_replace("Verzendkosten:", "",$row["SendCosts"])  > $hoogsteverzending){
                      $hoogsteverzending = str_replace("Verzendkosten:", "",$row["SendCosts"]);
                }
            }
        }
    }
    print('</div>
    <input class="ToevoegenWinkelmandbutton ToevoegenWinkelmandbutton1" type="submit" name='."submit".$artikelnummer.' value="Verwijderen">
    </form>');
    print("</div>");
    print("</div>");
    if (isset($_POST["submit".$artikelnummer])) {              // zelfafhandelend formulier
        $stockItemID = $artikelnummer;
        removeProductFromCart($stockItemID);         // maak gebruik van geïmporteerde functie uit cartfuncties.php
        }
    }
}
//if cart array is NOT empty print its content in the page
if($cart != null)
{
    print("<h1 style='color:black;margin-top:3%;'>Totaalprijs: €".$totaalprijs."</h1>");
    print("<h1 style='color:black'>Verzendkosten: €".$hoogsteverzending."</h1>");
    $totaal = int($totaalprijs) + ($hoogsteverzending);
    print("<h1 style='color:black'>Totaal: €".$totaal."</h1>");
}else
{
    print('<h1 style = "font-size:2.5vw;position:fixed; left:600px;color:Black;">Uw winkelmand is leeg</h1>');  //Tekst winkelmand is leeg, wanneer cart =0
    print('<form style = "method="get" action="index.php"> 
           <button style= "font-size:1vw;position:relative; left:720px;top:60px;color:Black;" type="submit">Homepagina</button></form>');  //Knop die leidt naar de homepage
}
?>
    </div>
</body>
</html>