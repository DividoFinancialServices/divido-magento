# divido-magento
Divido for Magento

## Uninstallation
Remove the following files and folders
```
app/code/community/Divido/Pay/
app/design/adminhtml/default/default/layout/divido_pay.xml
app/design/adminhtml/default/default/template/divido/
app/design/frontend/base/default/layout/divido.xml
app/design/frontend/base/default/template/pay/form/details.phtml
app/design/frontend/base/default/template/pay/widget.phtml
app/etc/modules/Divido_Payment.xml
js/Divido/
lib/Divido/
skin/frontend/base/default/css/Divido/
```

drop the table `<prefix>divido_lookup`

Remove any row with the prefix divido from the table `core_resource`

```
DELETE FROM `core_resource` WHERE `core_resource`.`code` = \'divido_pay_setup\'"
```
