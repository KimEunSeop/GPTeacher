#!/usr/bin/python3
"""
pdftotext를 사용한 PDF 텍스트 추출
라이브러리 의존성 없음
"""

import sys
import os
import subprocess
import tempfile

def main():
    if len(sys.argv) != 2:
        print("Error: PDF 파일 경로가 필요합니다.", file=sys.stderr)
        sys.exit(1)
    
    file_path = sys.argv[1]
    
    if not os.path.exists(file_path):
        print(f"Error: 파일을 찾을 수 없습니다: {file_path}", file=sys.stderr)
        sys.exit(1)
    
    print(f"PDF 파일 처리 시작: {file_path}", file=sys.stderr)
    
    try:
        # pdftotext 명령어 확인 - 전체 경로 사용
        pdftotext_paths = [
            '/opt/homebrew/bin/pdftotext',
            '/usr/local/bin/pdftotext',
            '/usr/bin/pdftotext'
        ]
        
        pdftotext_cmd = None
        for path in pdftotext_paths:
            if os.path.exists(path):
                pdftotext_cmd = path
                break
        
        if not pdftotext_cmd:
            # which 명령어로 한번 더 시도
            result = subprocess.run(['which', 'pdftotext'], 
                                  capture_output=True, text=True)
            if result.returncode == 0:
                pdftotext_cmd = result.stdout.strip()
        
        if not pdftotext_cmd:
            print("pdftotext가 설치되어 있지 않거나 찾을 수 없습니다.", file=sys.stderr)
            print("brew install poppler 명령어로 설치하세요.", file=sys.stderr)
            sys.exit(1)
        
        print(f"pdftotext 발견됨: {pdftotext_cmd}", file=sys.stderr)
        
        # 임시 텍스트 파일 생성
        with tempfile.NamedTemporaryFile(mode='w+', suffix='.txt', delete=False) as tmp_file:
            tmp_path = tmp_file.name
        
        # pdftotext 실행
        cmd = [pdftotext_cmd, '-layout', '-enc', 'UTF-8', file_path, tmp_path]
        result = subprocess.run(cmd, capture_output=True, text=True)
        
        if result.returncode == 0:
            # 추출된 텍스트 읽기
            with open(tmp_path, 'r', encoding='utf-8') as f:
                text = f.read()
            
            os.unlink(tmp_path)  # 임시 파일 삭제
            
            if len(text.strip()) < 50:
                print("Error: 추출된 텍스트가 너무 짧습니다.", file=sys.stderr)
                sys.exit(1)
            
            print(f"텍스트 추출 성공: {len(text)} 문자", file=sys.stderr)
            print(text.strip())  # stdout으로 출력
            
        else:
            print(f"pdftotext 오류: {result.stderr}", file=sys.stderr)
            sys.exit(1)
            
    except Exception as e:
        print(f"예외 발생: {str(e)}", file=sys.stderr)
        sys.exit(1)

if __name__ == "__main__":
    main()
    