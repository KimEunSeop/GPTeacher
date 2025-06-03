#!/usr/bin/env python3
import sys
import json
import urllib.request
import urllib.parse

def create_dynamic_prompt(text, total_questions, question_types):
    """사용자 설정에 따른 동적 프롬프트 생성"""
    
    # 문제 유형별 개수 계산
    if question_types == 'both':
        mc_count = total_questions // 2
        subj_count = total_questions - mc_count
    elif question_types == 'multiple_choice':
        mc_count = total_questions
        subj_count = 0
    else:  # subjective
        mc_count = 0
        subj_count = total_questions
    
    prompt = f"""당신은 전문적인 시험 문제 출제자입니다. 다음 텍스트를 바탕으로 정확히 {total_questions}개의 문제를 생성해주세요.

=== 텍스트 내용 ===
{text[:4000]}

=== 생성 요구사항 ===
- 총 문제 개수: {total_questions}개
- 객관식 문제: {mc_count}개 (4개 선택지, 정답 포함)
- 주관식 문제: {subj_count}개
- 문제는 텍스트 내용을 정확히 반영해야 함
- 각 문제는 명확하고 구체적이어야 함
- 난이도는 중급 수준으로 설정

=== 응답 형식 (JSON) ===
반드시 다음 JSON 형식으로만 응답해주세요:

{{
  "questions": ["""

    if mc_count > 0:
        prompt += """
    {
      "type": "multiple_choice",
      "number": 1,
      "question": "문제 내용을 여기에 작성",
      "choices": [
        "1) 첫 번째 선택지",
        "2) 두 번째 선택지", 
        "3) 세 번째 선택지",
        "4) 네 번째 선택지"
      ],
      "correct_answer": 2,
      "explanation": "정답 해설을 여기에 작성"
    },"""

    if subj_count > 0:
        prompt += """
    {
      "type": "subjective", 
      "number": 2,
      "question": "주관식 문제 내용을 여기에 작성",
      "sample_answer": "예시 답안을 여기에 작성",
      "grading_criteria": "채점 기준을 여기에 작성"
    }"""

    prompt += """
  ]
}

중요: 
1. 반드시 위 JSON 형식을 정확히 지켜주세요
2. 문제 번호는 1부터 순서대로 부여해주세요
3. 객관식 문제의 correct_answer는 1~4 중 하나의 숫자로 표시
4. 모든 문제는 제공된 텍스트 내용을 바탕으로 작성
5. JSON 외의 다른 텍스트는 포함하지 마세요"""

    return prompt

def call_openai_api(text, api_key, total_questions=5, question_types='both'):
    """OpenAI API를 호출하여 문제 생성"""
    
    prompt = create_dynamic_prompt(text, total_questions, question_types)
    
    # 요청 데이터 구성
    data = {
        'model': 'gpt-3.5-turbo',
        'messages': [
            {
                'role': 'system',
                'content': '당신은 전문적인 교육 평가 전문가입니다. 주어진 텍스트를 바탕으로 정확하고 의미있는 시험 문제를 생성합니다.'
            },
            {
                'role': 'user',
                'content': prompt
            }
        ],
        'max_tokens': 3000,
        'temperature': 0.7,
        'top_p': 0.9
    }
    
    try:
        # JSON으로 인코딩
        json_data = json.dumps(data).encode('utf-8')
        
        # HTTP 요청 생성
        req = urllib.request.Request(
            'https://api.openai.com/v1/chat/completions',
            data=json_data,
            headers={
                'Content-Type': 'application/json',
                'Authorization': f'Bearer {api_key}'
            }
        )
        
        # API 호출
        with urllib.request.urlopen(req, timeout=90) as response:
            response_data = json.loads(response.read().decode('utf-8'))
            
            api_response = response_data['choices'][0]['message']['content']
            
            # JSON 응답 검증
            try:
                # JSON 파싱 시도
                parsed_json = json.loads(api_response)
                
                # 필수 필드 검증
                if 'questions' not in parsed_json:
                    raise ValueError("Missing 'questions' field")
                
                if len(parsed_json['questions']) != total_questions:
                    print(f"Warning: Expected {total_questions} questions, got {len(parsed_json['questions'])}", file=sys.stderr)
                
                return api_response
                
            except json.JSONDecodeError as e:
                print(f"JSON Parse Error: {str(e)}", file=sys.stderr)
                print(f"Raw Response: {api_response[:500]}...", file=sys.stderr)
                return None
            
    except urllib.error.HTTPError as e:
        error_msg = e.read().decode('utf-8')
        print(f"HTTP {e.code}: {error_msg}", file=sys.stderr)
        return None
    except Exception as e:
        print(f"Exception: {str(e)}", file=sys.stderr)
        return None

if __name__ == "__main__":
    if len(sys.argv) < 3:
        print("Usage: python3 openai_api.py <text> <api_key> [total_questions] [question_types]", file=sys.stderr)
        sys.exit(1)
    
    text = sys.argv[1].strip()
    api_key = sys.argv[2].strip()
    total_questions = int(sys.argv[3]) if len(sys.argv) > 3 else 5
    question_types = sys.argv[4] if len(sys.argv) > 4 else 'both'
    
    # 받은 텍스트 내용 확인 (디버깅)
    print(f"Received text length: {len(text)}", file=sys.stderr)
    print(f"Received text preview: {text[:200]}...", file=sys.stderr)
    print(f"API key length: {len(api_key)}", file=sys.stderr)
    print(f"Total questions: {total_questions}", file=sys.stderr)
    print(f"Question types: {question_types}", file=sys.stderr)
    
    result = call_openai_api(text, api_key, total_questions, question_types)
    if result:
        # JSON 응답만 stdout으로 출력
        print(result)
    else:
        print("Error: API call failed", file=sys.stderr)
        sys.exit(1)