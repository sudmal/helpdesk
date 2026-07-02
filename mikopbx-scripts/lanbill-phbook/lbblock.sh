url=http://193.233.140.18:34012
header="Content-Type:text/xml;charset=UTF-8"
phone=`echo $1 |tr -d "+"`


curl -s -o /dev/null  -c /tmp/cookie-jar.txt --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Header/><soap:Body><urn:Login><login>REPLACE_WITH_LB_LOGIN</login><pass>REPLACE_WITH_LB_PASSWORD</pass></urn:Login></soap:Body></soap:Envelope>" $url
uid=`curl -s -b /tmp/cookie-jar.txt --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getAccounts xmlns=\"urn:api3\"><flt><phone xsi:type=\"xsd:string\">${phone}</phone></flt></getAccounts></soap:Body></soap:Envelope>" $url |sed -n 's/.*<uid>\(.*\)<\/uid>.*/\1/p'`
if [ -n "$uid" ];then
    block=`curl -s -b /tmp/cookie-jar.txt --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getVgroups xmlns=\"urn:api3\"><flt><userid xsi:type=\"xsd:int\">${uid}</userid></flt></getVgroups></soap:Body></soap:Envelope>" $url | xmllint --xpath '//*[local-name()="getVgroupsResponse"]/*[local-name()="ret"]/*[local-name()="blocked"]/text()' -`
fi
if [ -n "$block" ];then
    echo -en $block
else
    echo -en 0
fi
