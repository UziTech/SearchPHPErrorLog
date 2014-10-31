SearchPHPErrorLog
=================

Search php error log by terms or date

##Security Alert

####Make sure this is NOT visible to your public website. Your error logs could contain sensitive information and it would be easy to invoke a DOS attack with this script since it must read a potential large file.

###Inistallation

1. Download the zip and copy the /log/ folder to your server.
2. Browse to the folder and search your log file.

### Usage

Note: This will parse every line of your log file before it returns the results. Large log file will take some time.

#####Search

######Search for key words with excluding negative key words

```
(e.g. this -that "the other thing") 
Search for records containing "this" and "the other thing" but not containing "that"
```

#####Regular Expression

######Search for records that match a regular expression instead of key words

#####Match Case

######Match the case of the key words or regular expression

#####Reverse Order

######Show records with last reported at the bottom

#####Start Date

######Only show records that occured after this date & time

#####End Date

######Only show records that occured before this date & time

