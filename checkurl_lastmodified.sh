#!/bin/bash

url="$1"
filepath="$2"
email="$3"
msg_filepath="$4"
msg_append="$5"

curl --head $url | grep Last-Modified > ${filepath}new

if [ $? -ne 0 ]
then
	exit 1
fi

if [ -f $filepath ];
then
	cmp -s ${filepath}new ${filepath}
	if [ $? -eq 1 ]
	then
		echo "Change detected, sending notification(s)..."
		msg="Last-Modified for the following URL changed: $url"

		if [ -n "$msg_append" ]
		then
			msg="${msg} ${msg_append}"
		fi

		msg="${msg}  Old: `cat ${filepath}` New: `cat ${filepath}new`"

		if [ -n "$msg_filepath" ]
		then
			echo $msg >> $msg_filepath
		fi

		if [ -n "$email" ]
		then
			echo $msg | mail -s "URL monitor script" $email
		fi
	fi
fi

mv ${filepath}new ${filepath}

