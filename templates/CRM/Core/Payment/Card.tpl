{*
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC. All rights reserved.                        |
 |                                                                    |
 | This work is published under the GNU AGPLv3 license with some      |
 | permitted exceptions and without any warranty. For full license    |
 | and copyright information, see https://civicrm.org/licensing       |
 +--------------------------------------------------------------------+
*}
{* Manually create the CRM.vars.stripe here for drupal webform because \Civi::resources()->addVars() does not work in this context *}

{*{crmRegion name='form-body'}*}

{if $is_deductible }
    {*    {debug}*}
    <div class="crm-section">
        <div class="label">{$form.nric.label}</div>
        <div class="content">{$form.nric.html}</div>
        <div class="description">Note: this field is required if you would like a tax deduction.
            If you are making a contribution on behalf of someone else then please enter their NRIC.
        </div>
        <div class="clear"></div>
    </div>
    {*<div class="crm-section">*}
    {*    <div class="label">{$form.request3DS.label}</div>*}
    {*    <div class="content">{$form.request3DS.html}</div>*}
    {*    <div class="clear"></div>*}
    {*</div>*}

    {*{/crmRegion}*}

    {* JS HERE*}
    <script type="text/javascript">
        {literal}
        CRM.$(function ($) {
            'use strict';

            function assignAutoCompleteNRIC(id_field) {
                $('#' + id_field).on('change', function (event, data) {
                    var contactID = $(this).val();
                    // alert(contactID);
                    CRM.api3('Contact', 'get', {
                        "return": ["external_identifier"],
                        "id": contactID
                    }).then(function (result) {
                        // console.log(result)
                        $.each(result.values, function (id, value) {
                            $.each(value, function (fieldname, fieldvalue) {
                                // noinspection EqualityComparisonWithCoercionJS
                                if (fieldname == "external_identifier") {
                                    $('#nric').val(fieldvalue).change();
                                    console.log(fieldvalue)
                                }
                            })
                        })
                    }, function (error) {
                        console.log(error.toString())
                        // oops
                    });
                    // CRM.api3('profile', 'get', {'profile_id': profileids, 'contact_id': contactID})
                    //     .done(function (result) {
                    //             $.each(result.values, function (id, value) {
                    //                 $.each(value, function (fieldname, fieldvalue) {
                    //                     $('#' + fieldname).val(fieldvalue).change();
                    //                     $('[name="' + fieldname + '"]').val([fieldvalue]);
                    //                     if ($.isArray(fieldvalue)) {
                    //                         $.each(fieldvalue, function (index, val) {
                    //                             $("#" + fieldname + "_" + val).prop('checked', true);
                    //                         });
                    //                     }
                    //                 });
                    //             });
                    //         }
                    //     );
                });
            }

            function assignAutoCompleteContactNRIC(id_field, contactId) {
                $('#' + id_field).val(contactId).change();
            }

            if (CRM.form !== undefined) {
                $(CRM.form.autocompletes).each(function (index, autocomplete) {
                    assignAutoCompleteNRIC(autocomplete.id_field);
                });

                $('#nric').on('input', function (event, data) {

                    var nric = $(this).val();
                    if (!nric) {
                        return;
                    }
                    if (0 === nric.length) {
                        return;
                    }
                    if (!this.value) {
                        return;
                    }
                    // alert(nric);
                    CRM.api3('Contact', 'get', {
                        "return": ["id"],
                        "external_identifier": nric
                    }).then(function (result) {
                        // console.log(result);
                        $.each(result.values, function (id, value) {
                            $.each(value, function (fieldname, fieldvalue) {
                                // noinspection EqualityComparisonWithCoercionJS
                                if (fieldname == "id") {

                                    $(CRM.form.autocompletes).each(function (index, autocomplete) {
                                        assignAutoCompleteContactNRIC(autocomplete.id_field, fieldvalue);
                                    });
                                }
                            })
                        })
                    }, function (error) {
                        console.log(error.toString())
                        // oops
                    });


                });
            }
        });

        {/literal}
    </script>
{/if}