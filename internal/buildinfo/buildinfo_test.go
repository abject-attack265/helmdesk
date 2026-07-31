package buildinfo

import "testing"

func TestCompareVersionsUsesSemanticVersionPrecedence(t *testing.T) {
	tests := []struct {
		left     string
		right    string
		expected int
	}{
		{left: "1.2.3", right: "1.2.3", expected: 0},
		{left: "1.10.0", right: "1.9.9", expected: 1},
		{left: "1.2.3-beta.2", right: "1.2.3-beta.11", expected: -1},
		{left: "1.2.3-beta.999999999999999999999999", right: "1.2.3-beta.11", expected: 1},
		{left: "1.2.3-rc.1", right: "1.2.3", expected: -1},
		{left: "1.2.3+build.2", right: "1.2.3+build.1", expected: 0},
	}
	for _, test := range tests {
		actual, err := CompareVersions(test.left, test.right)
		if err != nil {
			t.Fatalf("CompareVersions(%q, %q) 返回错误: %v", test.left, test.right, err)
		}
		if actual != test.expected {
			t.Fatalf("CompareVersions(%q, %q) = %d，期望 %d", test.left, test.right, actual, test.expected)
		}
	}
}

func TestCompareVersionsRejectsInvalidValues(t *testing.T) {
	for _, value := range []string{
		"",
		"v1.2.3",
		" 1.2.3",
		"1.2",
		"1.02.3",
		"1.2.3-",
		"1.2.3-rc.01",
		"1.2.3+",
		"1.2.3+build..1",
		"1.2.3+build+1",
	} {
		if _, err := CompareVersions(value, "1.2.3"); err == nil {
			t.Fatalf("CompareVersions(%q, %q) 应拒绝无效版本", value, "1.2.3")
		}
	}
}
