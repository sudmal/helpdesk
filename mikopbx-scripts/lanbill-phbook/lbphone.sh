url=http://193.233.140.18:34012
header="Content-Type:text/xml;charset=UTF-8"
phone=`echo $1 |tr -d "+"`


curl -o /dev/null -s -c /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Header/><soap:Body><urn:Login><login>REPLACE_WITH_LB_LOGIN</login><pass>REPLACE_WITH_LB_PASSWORD</pass></urn:Login></soap:Body></soap:Envelope>" $url
uid=`curl -s -b /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getAccounts xmlns=\"urn:api3\"><flt><phone xsi:type=\"xsd:string\">${phone}</phone></flt></getAccounts></soap:Body></soap:Envelope>" $url |sed -n 's/.*<uid>\(.*\)<\/uid>.*/\1/p'`
if [ -n "$uid" ];then
    addr=`curl -s -b /tmp/cookie-jar.txt --connect-timeout 0.3 --header $header -d "<soap:Envelope xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:urn=\"urn:api3\"><soap:Body><getVgroups xmlns=\"urn:api3\"><flt><userid xsi:type=\"xsd:int\">${uid}</userid></flt></getVgroups></soap:Body></soap:Envelope>" $url | xmllint --xpath '//*[local-name()="getVgroupsResponse"]/*[local-name()="ret"]/*[local-name()="address"]/*[local-name()="address"]/text()' - |awk -F"," '{print $5" "$6" "$7" "$8}'`
fi

if [ -n "$addr" ];then
    echo -en "$addr"
else
    echo -en "$phone"
fi

curl -s --connect-timeout 3 --max-time 5 -X POST "https://vega8.ru/api/pbx/incoming"  -H "Authorization: Bearer REPLACE_WITH_PBX_TOKEN" --data-urlencode "phone=$1" --data-urlencode "address=$addr" > /dev/null 2>&1 || true

