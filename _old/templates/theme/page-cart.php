<?php 
if( !defined( 'CUSTOMER_PAGE' ) )
  exit;
require_once 'templates/'.$config['skin'].'/_header.php';

if( isset( $aData['sName'] ) ){
    require_once 'templates/'.$config['skin'].'/_title.php';
               // Sprawdzenie, czy jest komunikat
    
//$cart_message = $_COOKIE['cart_message'] ?? null;
 // Usunięcie komunikatu po pierwszym wyświetleniu
    
    
?>
<div class="container pageCart">
  <div class="mainPage__wrapper">
    <?php require_once 'templates/'.$config['skin'].'/_column.php'; ?>

        <div class="mainPage__content">
                      
             <?php echo ($aData['sDescriptionShort'] != ""  ? '<div class="mainPage__desc font-md">'.$aData['sDescriptionShort'].'</div>' : null); ?>

            
        <?php

        if ($_COOKIE['cart_message']){
            echo '<div class="alert alert-info">'.$_COOKIE['cart_message'].'</div>';
        }

        // Pobranie koszyka z cookies
        $cart = isset($_COOKIE['cart']) ? json_decode($_COOKIE['cart'], true) : [];
        $total_items = array_sum($cart);
        if (empty($cart)) {
            echo '<div class="alert alert-info"><img src="'.ICONS.'cart.svg" alt="Koszyk">Twój koszyk jest pusty.</div>';
        } else {
            
            $price_sum = "";
            
            
            
            echo '<div class="cartListWrapper">';
            if($total_items < 6){
                echo '<div class="alert alert-info mb-4">Zamówienie musi zawierać co najmniej 6 butelek.</div>';
            }
            
            echo '<ul class="cartList">';
            
            
        
            
            foreach ($cart as $product_id => $quantity) {
                $price_item = "";
                $thumb = getData($product_id, 'sFileName', 'files');
                $name = getData($product_id, 'sName');
                $link = $oPage->aPages[$product_id]['sLinkName'];
                $price_item = getData($product_id, 'sPrice') * $quantity;
                $price_sum = $price_sum + $price_item;
                
                    echo '<li class="cartItem">';
                    echo '<div class="cartItem__product">';
                        echo '<div class="cartItem__thumb">';
                            echo '<a href="'.$link.'" title="'.$name.'"><img src="files/920/'.$thumb.'" alt="'.$name.'" ></a>';
                        echo '</div>';
                        echo '<div class="cartItem__content">';
                            echo '<h5><a href="'.$link.'" title="'.$name.'">'.getData($product_id, 'sName').'</a></h5>';
                            echo '<p>'.$quantity.' x '.getData($product_id, 'sPrice').' zł</p>';
                        echo '</div>';
                    echo '</div>';
                
                
                 
                
                    echo '<div class="cartItem__info">';
                        echo '<div class="cartItem__quantity">';
                            echo '<button class="remove-from-cart quantity-minus" data-product-id="'.$product_id.'">-</button>
                            <input type="text" class="form-control" value="'.$quantity.'" readonly>
                            <button class="add-to-cart-1 quantity-plus" data-product-id="'.$product_id.'" data-action="add">+</button>';
                        echo '</div>';
                
                    echo '<div class="cartItem__price">'.$price_item.' zł</div>';
                
                    echo '<button class="delete-from-cart" href="" data-product-id="'.$product_id.'"><img src="'.ICONS.'close.svg" alt="Usuń" class="invert"></button>';
                
                    echo "</div>";
                echo "</li>";
            }

            echo "</ul>";
            
            
            echo '<div class="cartList__summary">';
            echo '<div class="cartList__price">'.$price_sum.' zł<small>+ kozty dostawy</small></div>';
            echo '<div class="cartList__button"><a href="'.$oPage->aPages[$config['order_page']]['sLinkName'].'" class="button button-lg">Dalej<img src="'.ICONS.'arrow-long.svg" class="ml-2 mr-0"></a></div>';
            echo "</div>";
            echo "</div>";
            
            
            
        
        }
 
            
                ?>

             <?php echo (($aData['sDescriptionFull'] != "" && $aData['sDescriptionFull'] != $aData['sDescriptionShort'] ) ? '<div class="mainPage__desc">'.$aData['sDescriptionFull'].'</div>' : null); ?>

             <?php echo $oFile->listFiles( $aData['iPage'] ); ?>
             

         <?php echo $oFile->listImages( $aData['iPage'], Array( 'iType' => 3, 'class' => 'galleryGrid')); ?>

         <?php echo ($aData['sDate'] ? '<div class="mainPage__date">Data publikacji '.$aData['sDate'].'</div>' : null); ?>

       </div>
   </div>
    <?php echo $oPage->listPages($aData['iPage'], array('hide_cat' => TRUE, 'type' => $aData['sType'], 'class' => 'pagesList-'.$aData['iPage'])); ?>   
</div>

<?php 
} else{ 
    require_once 'templates/'.$config['skin'].'/_404.php';
}



require_once 'templates/'.$config['skin'].'/_footer.php'; ?>