---
description: This is a simple project that generates excel reports from other existing excel reports
globs:
alwaysApply: true
---

# Project guidelines
So the whole basis of this project is to generate custom reports from existing excel reports. Basically user will upload an excel file with datasets, this file will be saved in the database so user can generate custom reports thereafter. We grab all the column titles( for example: customer, date, etc) and display to the user to tick/select which columns to be included in the new report, then also show them option to filter the rows that will be returned, and then they can click generate, then the system generates the new report to include the filtered rows with only the columns selected by the user.

We are making use of livewire too, so always prefer Livewire components

please always catch exceptions, use efficinet error logging and handling

