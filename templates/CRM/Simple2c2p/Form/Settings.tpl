<div class="crm-block crm-form-block">
    <table class="form-layout">
        <tbody>
        <tr>
            <td class="label">{$form.ok_url.label}</td>
            <td>{$form.ok_url.html}
                <br>
                <span class="description">URL for "Thank You" page</span></td>
        </tr>
        <tr>
            <td class="label">{$form.not_ok_url.label}</td>
            <td>{$form.not_ok_url.html}
                <br>
                <span class="description">URL for "Failed or Cancelled payment" page</span></td>
        </tr>
        <tr>
            <td class="label">{$form.save_log.label}</td>
            <td>{$form.save_log.html}
                <br>
                <span class="description">Save the log of all transactions in ConfigAndLog</span></td>
        </tr>
        </tbody>
    </table>
    <div class="crm-submit-buttons">
        {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>
</div>

