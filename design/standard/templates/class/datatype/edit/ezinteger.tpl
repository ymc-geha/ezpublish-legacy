<div class="block">
<label>{"Default value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_default_value_{$class_attribute.id}" value="{$class_attribute.data_int3}" size="8" maxlength="20" />
</div>

{switch name=input_state match=$class_attribute.data_int4}
  {case match=1}

<div class="block">
<div class="element">
<label>{"Min integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_min_integer_value_{$class_attribute.id}" value="{$class_attribute.data_int1}" size="8" maxlength="20" />
</div>
<div class="element">
<label>{"Max integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_max_integer_value_{$class_attribute.id}" value="" size="8" maxlength="20" />
</div>
<div class="break"></div>
</div>

  {/case}
  {case match=2}

<div class="block">
<div class="element">
<label>{"Min integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_min_integer_value_{$class_attribute.id}" value="" size="8" maxlength="20" />
</div>
<div class="element">
<label>{"Max integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_max_integer_value_{$class_attribute.id}" value="{$class_attribute.data_int2}" size="8" maxlength="20" />
</div>
<div class="break"></div>
</div>

  {/case}
  {case match=3}

<div class="block">
<div class="element">
<label>{"Min integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_min_integer_value_{$class_attribute.id}" value="{$class_attribute.data_int1}" size="8" maxlength="20" />
</div>
<div class="element">
<label>{"Max integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_max_integer_value_{$class_attribute.id}" value="{$class_attribute.data_int2}" size="8" maxlength="20" />
</div>
<div class="break"></div>
</div>

  {/case}
  {case}

<div class="block">
<div class="element">
<label>{"Min integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_min_integer_value_{$class_attribute.id}" value="" size="8" maxlength="20" />
</div>
<div class="element">
<label>{"Max integer value:"|i18n("design/standard/class/datatype")}</label><div class="labelbreak"></div>
<input type="text" name="ContentClass_ezinteger_max_integer_value_{$class_attribute.id}" value="" size="8" maxlength="20" />
</div>
<div class="break"></div>
</div>

  {/case}
{/switch}
