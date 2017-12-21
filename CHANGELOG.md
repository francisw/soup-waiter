# Changelog for Vacation Soup Waiter

## 0.2.12 (2017.12.08)

* **Enhancements**:
  - Added Latitude & Longitude to Destinations and Posts
  - Coloured required fields when being redirected for user input

* **Bug fixes**:
  - Removed duplicate destinations from Create screen caused by multiple properties at destination
  - Fixed UI fault when topics used up
  - Added Southern latitudes to Geography

## 0.2.11 (2017.12.03)

* **Enhancements**:
  - Added Category selector to new posts
  - Added multiple category select on create
  - Prefill password field effect

* **Bug fixes**:
  - Fixed fault that incorrectly handled quotes in house names (etc), as in Life O'Reilly
  - Fixed fault with Destinations not handling non-latin characters (as in São Paulo)
  - Fixed validation error when adding a second property
  - Corrected registration link on connect page
  - Fixed error in adding 3rd image as featured

## 0.2.10 (2017.11.22)

* **Bug fixes**:
  - Tags syndicating to Soup Kitchen correctly
  - Scheduled posting error fixed for exact dates
  - Improved links between kitchen and waiter

## 0.2.9 (2017.11.14)

* **Bug fixes**:
  - Fixed featured images from non-pixabay sources
  
## 0.2.8 (2017.11.05)

* **Bug fixes**:
  - Fixed posting fault labelled get_extra_permastruct
  - Fixed Syndication
  - Fixed erroneous path/url assumptions
  - Fixed erroneous saving of posts
  
## 0.2.7 (2017.11.04)

* **Bug fixes**:
  - Type coercion errors fixed
  
## 0.2.5 (2017.11.04)

* **Bug fixes**:
  - Improved Sanitisation, issues identified by WordPress team
  - Improved namespace polution, identified by WordPress team
  
## 0.2.4 (2017.11.04)

* **Enhancements**:
  - Wordpress Directory additions
  
## 0.2.3 (2017.11.02)

* **Enhancements**:
  - Added hyperlink to registration in connect
  
## 0.2.2 (2017.10.30)

* **Enhancements**:
  - Added Done button to most tabs, to improve flow
  - Force validation when clicking done
  - Added required* on necessary fields

* **Bug fixes**
  - Corrected auto-navigation of tabs on setup
  - Not displaying owners name on owners tab correctly
  - Re-added password field on connect screen

## 0.2.1 (2017.10.27)

* **Enhancements**:
  - Removed Social Connectors
  - Added alternate destination names

## 0.2.0 (2017.10.25)

* **Bug fixes**:
  - Fixed several errors related to handling a new (or empty) Kitchen

* **Enhancements**:
  - Made connect responsive
  - Syndicating Featured Image
  
## 1013.80 target (2017-10-09)

* **Enhancements**:
  - Removed Dashboard Tab
  - Reduced Card Title font-weight to normal (not bold, or 600)
  - Fixed occasional error alert on create, caused by ajax and page refresh clashing

## 1009.84 (2017-10-09)

* **Enhancements**:
  - Improved Service Check UI, and made it asynchronous
  - Added destination selection to Create Post
  - Added refresh button to Trending Searches to see more
  - Retain same topic list when changing destination on Create screen
  - Cleaned up Create layout & made responsive

## 1008.86 (2017-10-08)

* **Bug fixes**:
  - Fixed critical faults causing Waiter\property class not found, and PHP errors

## 1007.86 (2017-10-07)

* **Bug fixes**:
  - Fixed bug causing errant behaviour when clicking motd
  - Fixed visible input fields in social-container when not editing
  - Fixed some intermittent errors in new account creation
  - Fixed portrait images being available from Pixabay
  - Made Create Post featured Image responsive

* **Enhancements**:
  - Added auto-installer for Timber
  - Added installation instructions
  - Added simple service checks to connect tab
  - Enabled replacing the featured image by clicking on it
  - Moved Kitchen endpoint to core.vacationsoup.com
  - Implemented message of the day (MOTD) served from core
  - Implemented Trending Topics served from core
  - Refactor out old context code
  - Persist Post Topics and exclude from trending
  - Added VacationSoup as an autoTag example

## 1002.90 (2017-10-02)

Test release - not for consumption

## 0929.94 (2017-09-29)

Initial release for UI testing

* **Bug fixes**:
  - Lots and lots

* **Enhancements**:
  - Too many to mention
  
