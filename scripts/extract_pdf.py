#!/usr/bin/env python3
"""
PDF 텍스트 추출 스크립트
macOS XAMPP 환경용 - 수정된 버전
"""

import sys
import os

def extract_text_with_pypdf2(file_path):
    """PyPDF2를 사용한 텍스트 추출"""
    try:
        import PyPDF2
        with open(file_path, 'rb') as file:
            reader = PyPDF2.PdfReader(file)
            text = ""
            for page in reader.pages:
                text += page.extract_text() + "\n"
            return text.strip()
    except Exception as e:
        return f"PyPDF2 오류: {str(e)}"

def extract_text_with_fitz(file_path):
    """PyMuPDF(fitz)를 사용한 텍스트 추출"""
    try:
        import fitz
        doc = fitz.open(file_path)
        text = ""
        for page in doc:
            text += page.get_text() + "\n"
        doc.close()
        return text.strip()
    except Exception as e:
        return f"PyMuPDF 오류: {str(e)}"

def main():
    if len(sys.argv) != 2:
        print("Error: PDF 파일 경로가 필요합니다.")
        sys.exit(1)
    
    file_path = sys.argv[1]
    
    # 파일 존재 확인
    if not os.path.exists(file_path):
        print(f"Error: 파일을 찾을 수 없습니다: {file_path}")
        sys.exit(1)
    
    # PyMuPDF 먼저 시도
    text = extract_text_with_fitz(file_path)
    
    # PyMuPDF 실패시 PyPDF2 시도
    if text.startswith("PyMuPDF 오류") or len(text) < 30:
        text = extract_text_with_pypdf2(file_path)
    
    # 텍스트 길이 확인 (더 관대하게)
    if len(text) < 10:
        print("Error: Could not extract sufficient text from PDF")
        sys.exit(1)
    
    # 성공적으로 추출된 텍스트 출력
    print(text)

if __name__ == "__main__":
    main()
    