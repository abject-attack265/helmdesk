package main

import (
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"os"

	"helmdesk/internal/buildinfo"
	"helmdesk/internal/localization"
)

// runVersion 输出当前可执行文件的发布版本和构建来源。
func runVersion(arguments []string) int {
	flags := flag.NewFlagSet("version", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	jsonOutput := flags.Bool("json", false, localization.Text("output version information as JSON"))
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 0 {
		fmt.Fprintf(os.Stderr, localization.Text("version does not accept positional arguments: %s\n"), flags.Arg(0))
		return 2
	}
	info := buildinfo.Current()
	if *jsonOutput {
		encoder := json.NewEncoder(os.Stdout)
		encoder.SetEscapeHTML(false)
		if err := encoder.Encode(info); err != nil {
			fmt.Fprintln(os.Stderr, err)
			return 1
		}
		return 0
	}
	fmt.Printf("HelmDesk %s (%s/%s)\n", info.Version, info.OS, info.Arch)
	fmt.Printf(localization.Text("Commit: %s\n"), info.Commit)
	fmt.Printf(localization.Text("Built:  %s\n"), info.BuildDate)
	return 0
}
