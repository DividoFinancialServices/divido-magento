mkdir -p htdocs/magento/magento17
tar xvfj setup/magento-1.7.0.2.tar.bz2 -C htdocs/magento/magento17 --strip-components=1
cp setup/magento-1.7.0.2.local.xml htdocs/magento/magento17/app/etc/local.xml
mysql --port=33061 -h 127.0.0.1 -u root -proot magento17 < setup/magento-1.7.0.2.sql

mkdir -p htdocs/magento/magento18
tar xvfj setup/magento-1.8.1.0.tar.bz2 -C htdocs/magento/magento18 --strip-components=1
cp setup/magento-1.8.1.0.local.xml htdocs/magento/magento18/app/etc/local.xml
mysql --port=33061 -h 127.0.0.1 -u root -proot magento18 < setup/magento-1.8.1.0.sql

mkdir -p htdocs/magento/magento19
tar xvfj setup/magento-1.9.3.0.tar.bz2 -C htdocs/magento/magento19 --strip-components=1
cp setup/magento-1.9.3.0.local.xml htdocs/magento/magento19/app/etc/local.xml
mysql --port=33061 -h 127.0.0.1 -u root -proot magento19 < setup/magento-1.9.3.0.sql
