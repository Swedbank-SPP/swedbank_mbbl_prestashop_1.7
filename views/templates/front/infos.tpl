
<div class="alert alert-info">
<img src="../modules/swedbank/logo.png" style="float:left; margin-right:15px;" height="60">
    <b>This applies only for Card payment and Swedbank bank link</b>
    <p>Please provide <b>Notification url</b> to the bank: {$notificationUrl|escape:'htmlall':'UTF-8'}</p>
<br/>
    <p>Please configure cronjob for module to be able to receive order status updates from the payment provider. Recommend to run cronjob with 10 min intervals.</p>
<br/>
    <p>
    <ul>
        <li>{$cronTaskUrl|escape:'htmlall':'UTF-8'} <b>{l s='Execution via cronjob' mod='swedbank'}</b></li>
    </ul>
</p>
    <br>
    <b>Email modification</b>
    <p>
        Additional card payment details can be added to payment email by updating email template with
        {literal}{swedbank_html_block} placeholder for HTML templates and {swedbank_txt_block} placeholder for TXT templates.{/literal}
    </p>
</div>
