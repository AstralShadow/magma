@echo off

if [%1]==[run] (start /min php magma.php) else (
	if NOT [%1]==[] (php magmaController.php %*)
)