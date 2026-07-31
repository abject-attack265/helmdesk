; HelmDesk Windows 安装器负责部署命令行运行环境并保留业务数据。

#ifndef AppVersion
  #error AppVersion is required
#endif
#ifndef SourceDir
  #error SourceDir is required
#endif
#ifndef OutputDir
  #error OutputDir is required
#endif
#ifndef ChineseMessagesFile
  #error ChineseMessagesFile is required
#endif
#ifndef VcRedistMajor
  #error VcRedistMajor is required
#endif
#ifndef VcRedistMinor
  #error VcRedistMinor is required
#endif
#ifndef VcRedistBuild
  #error VcRedistBuild is required
#endif
#ifndef VcRedistRevision
  #error VcRedistRevision is required
#endif

[Setup]
AppId={{9D7352A3-6416-44F9-A97B-09FC3AE58658}
AppName=HelmDesk
AppVersion={#AppVersion}
AppPublisher=HelmDesk
DefaultDirName={autopf}\HelmDesk
DefaultGroupName=HelmDesk
DisableDirPage=yes
DisableProgramGroupPage=yes
UninstallDisplayIcon={app}\helmdesk.exe
OutputDir={#OutputDir}
OutputBaseFilename=HelmDesk-Setup
Compression=lzma2
SolidCompression=yes
ArchitecturesAllowed=x64compatible
ArchitecturesInstallIn64BitMode=x64compatible
PrivilegesRequired=admin
SetupLogging=yes
RestartApplications=no
WizardStyle=modern
; 安装器默认使用英文，并允许用户在启动时手动切换。
LanguageDetectionMethod=none
ShowLanguageDialog=yes

[Languages]
Name: "en"; MessagesFile: "compiler:Default.isl"
Name: "zhcn"; MessagesFile: "{#ChineseMessagesFile}"

[CustomMessages]
en.ConsoleShortcut=HelmDesk Console
zhcn.ConsoleShortcut=HelmDesk 控制台
en.OpenHelmDesk=Open HelmDesk
zhcn.OpenHelmDesk=打开 HelmDesk
en.UninstallHelmDesk=Uninstall HelmDesk
zhcn.UninstallHelmDesk=卸载 HelmDesk
en.PreparingResources=Preparing HelmDesk application resources...
zhcn.PreparingResources=正在准备 HelmDesk 应用资源...
en.OpenConsole=Open HelmDesk Console
zhcn.OpenConsole=打开 HelmDesk 控制台
en.VcRuntimeLaunchFailed=Unable to start the Microsoft Visual C++ Runtime installer
zhcn.VcRuntimeLaunchFailed=无法启动 Microsoft Visual C++ 运行时安装程序
en.VcRuntimeInstallFailed=Microsoft Visual C++ Runtime installation failed. Exit code:
zhcn.VcRuntimeInstallFailed=Microsoft Visual C++ 运行时安装失败，退出码：
en.DeleteBusinessData=Also delete all HelmDesk business data? This action cannot be undone.
zhcn.DeleteBusinessData=是否同时删除 HelmDesk 的全部业务数据？此操作不可恢复。

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Excludes: "vc_redist.x64.exe"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "{#SourceDir}\vc_redist.x64.exe"; Flags: dontcopy

[Dirs]
Name: "{commonappdata}\HelmDesk"; Permissions: users-modify

[Icons]
Name: "{group}\{cm:ConsoleShortcut}"; Filename: "{app}\helmdesk-console.cmd"; WorkingDir: "{app}"
Name: "{group}\{cm:OpenHelmDesk}"; Filename: "http://127.0.0.1:8080"
Name: "{group}\{cm:UninstallHelmDesk}"; Filename: "{uninstallexe}"
Name: "{commondesktop}\{cm:ConsoleShortcut}"; Filename: "{app}\helmdesk-console.cmd"; WorkingDir: "{app}"

[Run]
Filename: "{app}\helmdesk.exe"; Parameters: "help"; StatusMsg: "{cm:PreparingResources}"; Flags: runhidden waituntilterminated
Filename: "{app}\helmdesk-console.cmd"; Description: "{cm:OpenConsole}"; Flags: postinstall shellexec skipifsilent nowait runasoriginaluser

[Code]
// IsVersionAtLeast 判断已安装版本是否满足安装包携带的 VC++ 运行时版本。
function IsVersionAtLeast(Major, Minor, Build, Revision: Cardinal): Boolean;
begin
  Result :=
    (Major > {#VcRedistMajor}) or
    ((Major = {#VcRedistMajor}) and (Minor > {#VcRedistMinor})) or
    ((Major = {#VcRedistMajor}) and (Minor = {#VcRedistMinor}) and
      (Build > {#VcRedistBuild})) or
    ((Major = {#VcRedistMajor}) and (Minor = {#VcRedistMinor}) and
      (Build = {#VcRedistBuild}) and (Revision >= {#VcRedistRevision}));
end;

// IsVcRuntimeCurrent 检查系统已安装的 x64 VC++ v14 运行时版本。
function IsVcRuntimeCurrent: Boolean;
var
  Installed, Major, Minor, Build, Revision: Cardinal;
  RuntimeKey: String;
begin
  RuntimeKey := 'SOFTWARE\Microsoft\VisualStudio\14.0\VC\Runtimes\x64';
  Result :=
    RegQueryDWordValue(HKLM32, RuntimeKey, 'Installed', Installed) and
    (Installed = 1) and
    RegQueryDWordValue(HKLM32, RuntimeKey, 'Major', Major) and
    RegQueryDWordValue(HKLM32, RuntimeKey, 'Minor', Minor) and
    RegQueryDWordValue(HKLM32, RuntimeKey, 'Bld', Build) and
    RegQueryDWordValue(HKLM32, RuntimeKey, 'Rbld', Revision) and
    IsVersionAtLeast(Major, Minor, Build, Revision);
end;

// InstallVcRuntime 在部署 HelmDesk 前安装受系统统一维护的 VC++ 运行时。
function InstallVcRuntime(var NeedsRestart: Boolean): String;
var
  ResultCode: Integer;
begin
  Result := '';
  if IsVcRuntimeCurrent then
    exit;

  ExtractTemporaryFile('vc_redist.x64.exe');
  if not Exec(ExpandConstant('{tmp}\vc_redist.x64.exe'),
    '/install /quiet /norestart', '', SW_HIDE, ewWaitUntilTerminated,
    ResultCode) then
  begin
    Result := CustomMessage('VcRuntimeLaunchFailed') + ': ' +
      SysErrorMessage(ResultCode);
    exit;
  end;

  if ResultCode = 3010 then
    NeedsRestart := True
  else if ResultCode <> 0 then
    Result := CustomMessage('VcRuntimeInstallFailed') + ' ' +
      IntToStr(ResultCode) + '.';
end;

// PrepareToInstall 在写入应用文件前确保 VC++ 运行时可用。
function PrepareToInstall(var NeedsRestart: Boolean): String;
begin
  Result := InstallVcRuntime(NeedsRestart);
end;

// CurUninstallStepChanged 在交互卸载结束后确认是否删除业务数据。
procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if (CurUninstallStep = usPostUninstall) and (not UninstallSilent) and
    (MsgBox(CustomMessage('DeleteBusinessData'),
      mbConfirmation, MB_YESNO) = IDYES) then
    DelTree(ExpandConstant('{commonappdata}\HelmDesk'), True, True, True);
end;
