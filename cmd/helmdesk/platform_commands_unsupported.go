//go:build !linux && !windows

package main

func runPlatformCommand([]string) (bool, int) {
	return false, 0
}

func platformUsage() string {
	return ""
}
