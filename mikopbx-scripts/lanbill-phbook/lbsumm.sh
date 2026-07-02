url=http://193.233.140.18:34012
header="Content-Type:text/xml;charset=UTF-8"
phone=`echo $1 |tr -d "+"`


curl -o /dev/null -s -c /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Header/><soap:Body><urn:Login><login>REPLACE_WITH_LB_LOGIN</login><pass>REPLACE_WITH_LB_PASSWORD</pass></urn:Login></soap:Body></soap:Envelope>" $url
uid=`curl -s -b /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getAccounts xmlns=\"urn:api3\"><flt><phone xsi:type=\"xsd:string\">${phone}</phone></flt></getAccounts></soap:Body></soap:Envelope>" $url |sed -n 's/.*<uid>\(.*\)<\/uid>.*/\1/p'`
if [ -n "$uid" ];then
    addr=`curl -s -b /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getVgroups xmlns=\"urn:api3\"><flt><userid xsi:type=\"xsd:int\">${uid}</userid></flt></getVgroups></soap:Body></soap:Envelope>" $url | xmllint --xpath '//*[local-name()="getVgroupsResponse"]/*[local-name()="ret"]/*[local-name()="balance"]/*[local-name()="balance"]/text()' - |awk -F"," '{print $8}'`
fi

if [ -n "$addr" ];then
    echo -en "$addr"
else
    addr2=`curl --insecure --connect-timeout 0.3 -s -H "Content-Type: application/json" -d "{\"customer_contact_num\":\"${phone}\"}" https://212.66.62.23:1113/bl_billing.customer.get|sed -E 's/.*"address": *"([^"]*)".*/\1/'`
    if ! [[ "$addr2" =~ "errno" ]]; then
    	echo -en "$addr2"
    else
    	echo -en "$phone"
    fi
fi
