

<?php
   //logic goes here
  include "db_connection.php";

 //Get the current date to use for the order information
 $tm = time();
 $passed = true; //did anything fail?
 $total = 0.00; //total so far of cost
 $local = 0; // is the order local?
date_default_timezone_set('America/Chicago');
  //Store the warehouse, district, and customer data from the order form
  $dID = $_POST['D_ID'];
  $wID = $_POST['W_ID'];
  $cID = $_POST['C_ID'];

  //Query and results to use for the warehouse information
  $warehouseSQL = "SELECT * FROM warehouse WHERE W_ID = $wID";
  $warehouseResult = mysqli_query($mysqli, $warehouseSQL);
  $warehouseRow = mysqli_fetch_assoc($warehouseResult) or die("Warehouse doesnt exist"); //Validate warehouse ID exists

  //Query and results to use for the district information
  $districtSQL = "SELECT * FROM district WHERE D_ID = $dID";
  $districtResult = mysqli_query($mysqli, $districtSQL); 
  $districtRow = mysqli_fetch_assoc($districtResult) or die("District doesnt exist"); //Validate district ID exists

  //Query and results to use for the customer information
  $customerSQL = "SELECT * FROM customer WHERE C_ID = $cID";
  $customerResult = mysqli_query($mysqli, $customerSQL);
  $customerRow = mysqli_fetch_assoc($customerResult) or die("Customer doesnt exist"); //Validate customer ID exists

  //There is also the orders item information - OL_I_ID#, OL_SUPPLY_W_ID#, OL_QUANTITY#

  //print_r($_POST); 
   ///fetch_assoc one
   // fetch_all

   $array = $_POST;
   $rowcount = ((sizeof($array)) - 3) /3; // subtract for 3 above then divide by 3 because each row has 3 columns
   //go through all orderlines and check if local order or not
    for($x =1;$x<=$rowcount;$x++){
       if($array['OL_SUPPLY_W_ID'.$x] != $wID){
         $local = 1;
       }
    }
   //Add orderr row into tables. Entry date will have to be generated. can't do first since we can't check if local till for loop
  //O_ID  O_D_ID   O_W_ID   O_C_ID   O_ENTRY_D   O_CARRIER_ID   O_OL_CNT O_ALL_LOCAL
  //disrict_next_id  index    index    index    generate    sql query      row count     if all 0 or not        

  //get D_NEXT_O_ID
  $O_IDq = "SELECT D_NEXT_O_ID FROM DISTRICT WHERE D_ID = $dID and D_W_ID = $wID";//get next order number
  $O_ID = mysqli_query($mysqli, $O_IDq );
  $D_NEXT_O_ID = mysqli_fetch_assoc($O_ID);// get the next order number for this district and warehouse
  $D_NEXT_O_ID = $D_NEXT_O_ID['D_NEXT_O_ID']; //set variable to it since we use it multiple times
  $UPDATEQ = "UPDATE DISTRICT SET D_NEXT_O_ID = D_NEXT_O_ID  + 1 WHERE D_ID = $dID AND D_W_ID = $wID";//INCREASE THE NEXT ID
  $UPDATER = mysqli_query($mysqli,$UPDATEQ);

  // insert into orderr
  $ORDERRSQL = "INSERT INTO ORDERR (O_ID,O_D_ID,O_W_ID,O_C_ID,O_ENTRY_D,O_CARRIER_ID,O_OL_CNT,O_ALL_LOCAL)	
  VALUES ($D_NEXT_O_ID,$dID,$wID,$cID,CURDATE(),NULL,$rowcount,$local)";
  $ORDDERRR = mysqli_query($mysqli,$ORDERRSQL);

  //insert into new order
  $NEWORSQL = "INSERT INTO NEW_ORDER (NO_O_ID,NO_D_ID,NO_W_ID) VALUES($D_NEXT_O_ID,$dID,$wID)";
  $NEWORDERR = mysqli_query($mysqli,$NEWORSQL);
  
  //go through all order lines and create orderline for database
  $array = $_POST;
  $rowcount = ((sizeof($array)) - 3) /3; // subtract for 3 above then divide by 3 because each row has 3 columns
  //go through all orderlines
   for($x =1;$x<=$rowcount;$x++){
      $I_IDq = "SELECT * FROM ITEM WHERE I_ID =".$array['OL_I_ID'.$x]; ///get next order number
      $I_ID = mysqli_query($mysqli, $I_IDq );//using OL_ID
      $I_ROW = mysqli_fetch_assoc($I_ID);//GET THE ITEM ROW
      if($I_ID->num_rows ==0){ // if the item number isn't valid
         $passed= false;
         break;
      }else{ // it's found
         $price = $I_ROW['I_PRICE']; //item price
         $name = $I_ROW['I_NAME']; //item name
         $data = $I_ROW['I_DATA']; //item data
      }
      $S_IDq = "SELECT * FROM STOCK WHERE S_I_ID =".$array['OL_I_ID'.$x]." and S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x]; ///checkl stock of that item in supply warehouse it has to exixt because it passed item check already stock is full of items that exist
      $S_ID = mysqli_query($mysqli, $S_IDq );//using OL_ID
      $S_ROW = mysqli_fetch_assoc($S_ID);//GET THE ITEM ROW
      $stockq = $S_ROW['S_QUANTITY']; //how many of that item are in stock
      $stockd = $S_ROW['S_DATA']; //stock data
      if($dID <10 ){ //not district ten we need to do this because districts don't have a 0 infront of them 
         $stockdt = $S_ROW['S_DIST_0'.$dID]; // stock district number equals this 
      }else{
         $stockdt = $S_ROW['S_DIST_'.$dID];
      }
      if($stockq > $array['OL_QUANTITY'.$x] + 10){ // if the stockq is greater then how many being order +10
         $STOCKUPDATEq = "UPDATE STOCK SET S_QUANTITY = S_QUANTITY - ".$array['OL_QUANTITY'.$x]." WHERE ". $I_ROW['I_ID']. " = ".$S_ROW['S_I_ID']." AND S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x];//REMOVE THAT MUCH FROM THAT STOCK HOPEFULLY UNTESTED
         $UPDATERsq = mysqli_query($mysqli,$STOCKUPDATEq);
      }else{
         $STOCKUPDATEq = "UPDATE STOCK SET S_QUANTITY = (S_QUANTITY - ".$array['OL_QUANTITY'.$x].") + 91 WHERE ". $I_ROW['I_ID']. " = ".$S_ROW['S_I_ID']." AND S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x];//REMOVE THAT MUCH FROM THAT STOCK and then add 91 untested
         $UPDATERsq = mysqli_query($mysqli,$STOCKUPDATEq);
      }
      $STOCKUPDATEytd = "UPDATE STOCK SET S_YTD = (S_YTD + ".$array['OL_QUANTITY'.$x].") WHERE ". $I_ROW['I_ID']. " = ".$S_ROW['S_I_ID']." AND S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x];//ADD TO THE YTD untested
      $UPDATERytd = mysqli_query($mysqli,$STOCKUPDATEytd);
      $STOCKUPDATEoc = "UPDATE STOCK SET S_ORDER_CNT = S_ORDER_CNT + 1 WHERE ". $I_ROW['I_ID']. " = ".$S_ROW['S_I_ID']." AND S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x];//ADD TO THE YTD untested
      $UPDATERoc = mysqli_query($mysqli,$STOCKUPDATEoc );
      if($array['OL_SUPPLY_W'.$x] != $wID){//not from same warehouse
         $STOCKUPDATEoc = "UPDATE STOCK SET S_REMOTE_CNT = S_REMOTE_CNT + 1 WHERE ". $I_ROW['I_ID']. " = ".$S_ROW['S_I_ID']." AND S_W_ID = ".$array['OL_SUPPLY_W_ID'.$x];//ADD TO THE REMOTE COUNT
         $UPDATERoc = mysqli_query($mysqli,$STOCKUPDATEoc );
      }
      if(str_contains($data,"ORIGINAL") && str_contains($stockd,"ORIGINAL")){
         $ITEMDATA = "UPDATE item SET I_DATA = 'B' " ;//change the brand generic data think it's the item data???
         $UPDATEIDATA = mysqli_query($mysqli, $ITEMDATA );
      }else{
         $ITEMDATA = "UPDATE item SET I_DATA = 'G' " ;//change the brand generic data think it's the item data???
         $UPDATEIDATA = mysqli_query($mysqli, $ITEMDATA );
         
      }
      $OLAMOUNT = $array['OL_QUANTITY'.$x] * $price; //oderline total
      $total = $total + $OLAMOUNT; //increase total variable for later 
      $ORDERLINESQL = "INSERT INTO ORDER_LINE 
      OL_O_ID	OL_D_ID	OL_W_ID	OL_NUMBER	OL_I_ID	OL_SUPPLY_W_ID	OL_DELIVERY_D	OL_QUANTITY	OL_AMOUNT	OL_DIST_INFO)	
      VALUES ($D_NEXT_O_ID,$dID,	$wID,$x,".$array['OL_I_ID'.$x].",".$array['OL_SUPPLY_W'.$x].",NULL,".$array['OL_QUANTITY'.$x].",".$OL_AMOUNT.",".$stockdt.")";
      $ORDDERLINE = mysqli_query($mysqli,$ORDERLINESQL ); //INSERT ORDERLINE INTO ORDER LINE TABLE

      // OL_I_ID1
      // OL_SUPPLY_W_ID1
      // OL_QUANTITY1

   }
    //use this $D_NEXT_O_ID to show the order table query
   //TOTAL 
   $total = $total *(1-$customerRow['C_DISCOUNT']) * (1+$warehouseRow['W_TAX'] +$districtRow['D_TAX']);
   $endtime = time();
   $totaltime = $endtime-$tm;
   echo($passed);

   //check if passed if it did display all info
   //else rollback the data and just display top


  //Stock will have to be updated for each item


  //then display with html & php below
  //answers go here
?>


<!DOCTYPE html>
<html>
   <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <link rel="stylesheet" href="resultStyles.css">
      <link rel="stylesheet" href="normalize.css">
      <link rel="stylesheet" href="styles.css">
      <title>Results</title>
   </head>
   <body>
      <h1> New Order Results </h1>

      <table id="top_table">
         <tbody>
            <tr>
               <td> - </td>
               <td> - </td>
               <td colspan="2"> New Order </td>
            </tr>
            <tr>
               <td> Warehouse: 
                  <?php echo "{$warehouseRow['W_ID']}"; //do we need 0001/0002 or is 1/2 okay? ?>  
               </td> 
               <td> District: <?php echo "{$districtRow['D_ID']}"; ?> </td>
               <td colspan="2"> Date: <?php echo date("m-d-Y H:i:s", $tm); ?> </td>
            </tr>
            <tr>
               <td> Customer: <?php echo "{$customerRow['C_ID']}"; ?> </td>
               <td> Name: <?php echo "{$customerRow['C_FIRST']}" . " " . "{$customerRow['C_LAST']}"; ?> </td>
               <td> Credit: <?php echo "{$customerRow['C_CREDIT']}" ?> </td>
               <td> Disc: <?php echo "{$customerRow['C_DISCOUNT']}" ?> </td>
            </tr>
            <tr>
               <td> Order Number: </td>
               <td> Number of lines: <?php echo $rowcount; ?></td>
               <td> W_tax: <?php echo "{$warehouseRow['W_TAX']}"; ?> </td>
               <td> D_tax: <?php echo "{$districtRow['D_TAX']}"; ?> </td>
            </tr>
         </tbody>
      </table>


      <hr>

      <table id="bottom_table">
         <thead>
            <tr>
               <th>Supp_W</th>
               <th>Item_id</th>
               <th>Item_Name</th>
               <th>Qty</th>
               <th>Stock</th>
               <th>B/G</th>
               <th>Price</th>
               <th>Amount</th>
            </tr>
         </thead>
         <tbody>
            <tr>
               <td id="OL_SUPPLY_W_ID"> placeholder </td>
               <td id="OL_I_ID"> placeholder </td>
               <td id="I_NAME"> placeholder </td>
               <td id="OL_QUANTITY"> placeholder </td>
               <td id="S_QUANTITY"> placeholder </td>
               <td> placeholder  </td>
               <td id="I_PRICE"> placeholder </td>
               <td id="OL_AMOUNT"> placeholder </td>
            </tr>
            <tr>
               <td id="OL_SUPPLY_W_ID"> placeholder </td>
               <td id="OL_I_ID"> placeholder </td>
               <td id="I_NAME"> placeholder </td>
               <td id="OL_QUANTITY"> placeholder </td>
               <td id="S_QUANTITY"> placeholder </td>
               <td> placeholder  </td>
               <td id="I_PRICE"> placeholder </td>
               <td id="OL_AMOUNT"> placeholder </td>
            </tr>
            <tr>
            </tr>
            <tr>
            </tr>
            <tr>
            </tr>
         </tbody>
      </table>

      <hr>

      <a href="index.php">Return to homepage</a>
         <p> <?php 
          
            ?> 
         </p>
   </body>
</html>
