# checkvist-ical-optimizer
recognize times in ical tasks and reformat the .ics file to correct start and end times.

usage: 
put this .php file in your webserver and call the url in the browser. 
you get a form where you can enter the ical url. 

get the ical subscription-link from checkvist by getting the ical link from the due-date popup. 
uncheck the checkbox (off) with the option "export full day events" as the converter only changes single events. 

past the url into the form field and click submit. 

now you can see the optimized output. 

simply copy the new url from the browser and use it in your calender app instead of the direct
url from checkvist. 

the proxy is dynamic, so everytime your calender app is fetching the .ics it will get the optimized
version. 


