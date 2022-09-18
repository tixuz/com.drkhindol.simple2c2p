{*  This form redirects to payment portal  *}
{* don't touch, don't edit *}

<html>
  <body>
    <form action="{$webPaymentUrl}" method="get">
    </form>
    {literal}
      <script type="text/javascript">
        function submitForm(){
          var form = document.forms[0];
          form.submit();
        }
        submitForm();
      </script>
    {/literal}
</html>
