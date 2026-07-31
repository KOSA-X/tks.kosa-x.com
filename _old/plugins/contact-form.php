<?php

?>

#komunikat#

<form action="#strona##formularz" method="post" class="form pageContact__form" enctype="multipart/form-data">
    <div id="formularz" class="form-id"></div>


    <div class="form-floating form-item">
        <input type="text" class="form-control" id="name" name="name" value="#name#" placeholder="Imię i nazwisko" required>
        <label for="name">Imię i nazwisko / nazwa <span class="text-danger">*</span></label>
    </div>

    <div class="row">
        <div class="col-6">
            <div class="form-floating form-item">
                <input type="text" class="form-control" id="phone" name="phone" value="#phone#" placeholder="Numer telefonu" required>
                <label for="phone">Telefon <span class="text-danger">*</span></label>
            </div>
        </div>
        <div class="col-6">
            <div class="form-floating form-item">
                <input type="email" class="form-control" id="email" name="email" value="#email#" placeholder="Adres e-mail" required>
                <label for="email">E-mail <span class="text-danger">*</span></label>
            </div>
        </div>
    </div>

    <div class="form-floating form-item">
        <textarea name="message" class="form-control" rows="4" id="message"  placeholder="W czym możemy pomóc?">#message#</textarea>
        <label for="message" class="col-form-label">Wiadomość</label>
    </div>
 
    <div class="form-check form-item">
        <input class="form-check-input" type="checkbox" value="" id="regulamin" name="regulamin"  required>
        <label class="form-check-label" for="regulamin">
        <span class="text-danger">*</span> Akcetpuję warunki zawarte w <a href="<?php echo $oPage->aPages[17]['sLinkName']; ?>" class="link_underline" target="_blank"><?php echo $oPage->aPages[17]['sName']; ?></a> oraz w <a href="<?php echo $oPage->aPages[18]['sLinkName']; ?>" class="link_underline" target="_blank"><?php echo $oPage->aPages[18]['sName']; ?></a>. 
        </label>
    </div>

    <?php if($config['publicKey'] != ""){ ?>
        <input type="hidden" id="g-recaptcha-response" name="g-recaptcha-response">
        <input type="hidden" name="action" value="validate_captcha">
    <?php } ?>
    <div class="form-button">
        <button type="submit" id="submit" name="submit" class="button">Wyślij</button>
    </div>
</form>