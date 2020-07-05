#!/bin/bash
PACKAGE_NAME="dev.openauth.wsc.login"
rm -f acptemplates.tar
rm -f files.tar
rm -f templates.tar
rm -f ${PACKAGE_NAME}.tar
7z a -ttar -mx=9 acptemplates.tar ./acptemplates/*
7z a -ttar -mx=9 files.tar ./files/*
7z a -ttar -mx=9 templates.tar ./templates/*
7z a -ttar -mx=9 ${PACKAGE_NAME}.tar ./* -x!acptemplates -x!files -x!templates -x!${PACKAGE_NAME}.tar -x!.git -x!.gitignore -x!make.bat -x!make.sh -x!.vscode -x!.idea -x!constants.php
rm -f acptemplates.tar
rm -f files.tar
rm -f templates.tar
