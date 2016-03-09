echo "This will remove all files, database tables and EAV-attributes installed by Divido."
read -r -p "Are you sure? [y/N] " response
response=${response,,}    # tolower
if [[ $response =~ ^(yes|y)$ ]]
then 
	rm -r app/code/community/Divido
	rm -r app/design/adminhtml/default/default/layout/divido_pay.xml
	rm -r app/design/frontend/base/default/layout/divido.xml
	rm -r app/design/frontend/base/default/template/catalog/product/divido_calculator.phtml
	rm -r app/design/frontend/base/default/template/catalog/product/divido_widget.phtml
	rm -r app/design/frontend/base/default/template/pay
	rm -r app/etc/modules/Divido_Payment.xml
	rm -r js/Divido
	rm -r lib/Divido
	rm -r skin/frontend/base/default/css/Divido
	rm -r divido_callback.php
	
	php divido_uninstall_db.php
fi
